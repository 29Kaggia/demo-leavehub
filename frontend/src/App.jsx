import { useCallback, useEffect, useState } from 'react'
import { jsPDF } from 'jspdf'
import './App.css'

const API = '/api'
const nameOf = (user) => `${user.first_name} ${user.last_name}`
const formatDate = (value) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`))

async function api(path, options = {}) {
  const response = await fetch(`${API}${path}`, { cache: 'no-store', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options })
  const body = await response.text()
  let payload
  try { payload = body ? JSON.parse(body) : {} } catch { throw new Error('The server returned an invalid response. Please check that the backend is running.') }
  if (!response.ok) throw new Error(payload.message || 'Something went wrong.')
  return payload
}

function RequestTable({ requests, admin, onSelect }) {
  if (!requests.length) return <p className="empty">No leave requests yet.</p>
  return <div className="table-wrap"><table><thead><tr>{admin && <th>Employee</th>}<th>Leave type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead><tbody>{requests.map((request) => <tr className="clickable-row" key={request.id} onClick={() => onSelect(request)} onKeyDown={(event) => event.key === 'Enter' && onSelect(request)} tabIndex="0">{admin && <td>{nameOf(request.employee)}</td>}<td>{request.leave_type?.name}</td><td>{formatDate(request.start_date)} – {formatDate(request.end_date)}</td><td>{request.requested_days}</td><td><span className={`status ${request.status}`}>{request.status.replaceAll('_', ' ')}</span></td></tr>)}</tbody></table></div>
}

function LegacyRequestDetails({ request, onClose, onUpdated }) {
  const [cancellationReason, setCancellationReason] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  if (!request) return null
  const canCancel = ['pending_relief_officer', 'pending_line_manager', 'approved'].includes(request.status)
  async function cancel() {
    if (!cancellationReason.trim()) { setError('Please provide a reason for cancelling this request.'); return }
    setBusy(true); setError('')
    try { await api(`/leave-requests/${request.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason: cancellationReason }) }); window.dispatchEvent(new Event('leave-request-updated')); await onUpdated?.(); onClose() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }
  return <div className="modal-backdrop" onMouseDown={onClose}><section className="request-modal" onMouseDown={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={onClose}>×</button><p className="eyebrow">Leave request</p><h2>{request.leave_type?.name}</h2><dl><div><dt>Dates</dt><dd>{formatDate(request.start_date)} – {formatDate(request.end_date)}</dd></div><div><dt>Days requested</dt><dd>{request.requested_days}</dd></div><div><dt>Status</dt><dd><span className={`status ${request.status}`}>{request.status.replaceAll('_', ' ')}</span></dd></div>{request.reason && <div><dt>Reason</dt><dd>{request.reason}</dd></div>}<div><dt>Handover notes</dt><dd>{request.handover_notes}</dd></div></dl>{canCancel && <section className="cancellation-panel"><strong>Need to withdraw this request?</strong><label>Cancellation reason<textarea rows="2" value={cancellationReason} onChange={(event) => setCancellationReason(event.target.value)} placeholder="Explain why you need to cancel" /></label>{error && <p className="alert error">{error}</p>}<button type="button" className="reject" disabled={busy} onClick={cancel}>{busy ? 'Cancelling…' : 'Cancel leave request'}</button></section>}</section></div>
}

function AuthScreen({ onAuthenticated }) {
  const [mode, setMode] = useState('login')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [signupEnabled, setSignupEnabled] = useState(true)
  useEffect(() => {
    api('/auth/options').then(({ data }) => {
      setSignupEnabled(Boolean(data.signup_enabled))
      if (!data.signup_enabled) setMode('login')
    }).catch(() => {})
  }, [])
  async function submit(event) {
    event.preventDefault(); setError(''); setBusy(true)
    try { const result = await api(`/auth/${mode}`, { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); await onAuthenticated(result.data) } catch (requestError) { setError(requestError.message === 'Failed to fetch' ? 'Cannot reach the backend. Start the API server and try again.' : requestError.message) } finally { setBusy(false) }
  }
  return <main className="auth-page"><section className="auth-intro"><img className="auth-logo" src="/kagia-codes-logo.png" alt="Kagia Codes" /><p className="eyebrow">People operations</p><h1>LeaveHub</h1><h2>Leave made simple.</h2><p>Request time away and track its approval.</p></section><section className="auth-card"><div className="tabs"><button className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')} type="button">Sign in</button>{signupEnabled && <button className={mode === 'signup' ? 'active' : ''} onClick={() => setMode('signup')} type="button">Create organization</button>}</div><h2>{mode === 'login' ? 'Welcome back' : 'Set up your organization'}</h2>{!signupEnabled && <p className="auth-help">Account creation is disabled. Contact your HR administrator if you need an account.</p>}{error && <p className="alert error">{error}</p>}<form className="auth-form" onSubmit={submit}>{mode === 'signup' && <><label>Organization name<input name="organization_name" required maxLength="160" /></label><div className="dates"><label>First name<input name="first_name" required /></label><label>Last name<input name="last_name" required /></label></div></>}<label>Work email<input name="email" type="email" required /></label><label>Password<input name="password" type="password" minLength="8" required /></label><button disabled={busy}>{busy ? 'Please wait…' : mode === 'login' ? 'Sign in' : 'Create organization'}</button></form></section></main>
}

function OrganizationPayrollSettings() {
  const [organization, setOrganization] = useState(null)
  const [employees, setEmployees] = useState([])
  const [employeeId, setEmployeeId] = useState('')
  const [payProfile, setPayProfile] = useState(null)
  const [loadingOrganization, setLoadingOrganization] = useState(true)
  const [loadingPayProfile, setLoadingPayProfile] = useState(false)
  const [payProfileRetry, setPayProfileRetry] = useState(0)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const loadOrganization = useCallback(async () => {
    setLoadingOrganization(true); setError('')
    try {
      const [orgResult, employeeResult] = await Promise.all([api('/organization'), api('/employees')])
      setOrganization(orgResult.data)
      setEmployees(employeeResult.data)
      setEmployeeId(String(employeeResult.data[0]?.id || ''))
    } catch (requestError) { setError(requestError.message) } finally { setLoadingOrganization(false) }
  }, [])
  useEffect(() => { loadOrganization() }, [loadOrganization])
  useEffect(() => {
    if (!employeeId || !organization) { setPayProfile(null); setLoadingPayProfile(false); return }
    let active = true
    setPayProfile(null); setLoadingPayProfile(true); setError('')
    api(`/employees/${employeeId}/pay-profile`).then(({ data }) => {
      if (active) setPayProfile(data || { base_salary: '0.00', fixed_allowances: '0.00', recurring_deductions: '0.00', pay_frequency: organization.pay_frequency || 'monthly', effective_from: new Date().toISOString().slice(0, 10) })
    }).catch((requestError) => { if (active) setError(requestError.message) }).finally(() => { if (active) setLoadingPayProfile(false) })
    return () => { active = false }
  }, [employeeId, organization?.pay_frequency, payProfileRetry])
  async function saveOrganization(event) {
    event.preventDefault(); setError(''); setNotice('')
    try { const result = await api('/organization', { method: 'PATCH', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); setOrganization(result.data); setNotice('Organization settings saved.') } catch (requestError) { setError(requestError.message) }
  }
  async function savePayProfile(event) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try { await api(`/employees/${employeeId}/pay-profile`, { method: 'PUT', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); setNotice('Employee pay details saved.') } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }
  if (loadingOrganization) return <main className="loading">Loading organization settings…</main>
  if (!organization) return <section className="payroll-load-error"><h1>Couldn’t load payroll settings</h1><p>{error || 'The organization settings could not be loaded.'}</p><button type="button" onClick={loadOrganization}>Try again</button></section>
  return <><section className="page-heading payroll-heading"><div><p className="eyebrow">People operations · HR admin</p><h1>Organization &amp; payroll</h1><p className="page-summary">Set company-wide pay defaults and maintain each employee’s confidential pay profile.</p></div><span className="payroll-secure-badge">🔒 HR admin only</span></section>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<section className="payroll-section"><header className="payroll-section-heading"><span className="payroll-section-icon">⌂</span><div><p className="eyebrow">Company defaults</p><h2>Organization settings</h2><p>These settings provide the default context for payroll across your organization.</p></div></header><form className="payroll-form organization-form" onSubmit={saveOrganization}><label className="payroll-name-field">Organization name<input name="name" required maxLength="160" defaultValue={organization.name} placeholder="Your organization" /></label><label>Country code<input name="country_code" required maxLength="2" minLength="2" pattern="[A-Za-z]{2}" title="Enter a two-letter country code, such as KE." defaultValue={organization.country_code} placeholder="KE" /></label><label>Currency code<input name="currency_code" required maxLength="3" minLength="3" pattern="[A-Za-z]{3}" title="Enter a three-letter currency code, such as KES." defaultValue={organization.currency_code} placeholder="KES" /></label><label>Default pay frequency<select name="pay_frequency" defaultValue={organization.pay_frequency}><option value="monthly">Monthly</option><option value="biweekly">Every two weeks</option><option value="weekly">Weekly</option></select></label><div className="payroll-form-footer"><p>New employee pay profiles can use this default frequency.</p><button>Save organization settings</button></div></form></section><section className="payroll-section employee-payroll-section"><header className="payroll-section-heading"><span className="payroll-section-icon employee-payroll-icon">▤</span><div><p className="eyebrow">Confidential employee records</p><h2>Employee pay details</h2><p>Choose an employee to view or update their pay terms and effective date.</p></div></header><div className="payroll-employee-picker"><label htmlFor="payroll-employee">Employee</label><select id="payroll-employee" required value={employeeId} onChange={(event) => { setError(''); setPayProfile(null); setEmployeeId(event.target.value) }}><option value="">Choose employee</option>{employees.map((employee) => <option key={employee.id} value={employee.id}>{nameOf(employee)} · {employee.employee_number}</option>)}</select></div>{!employees.length && <p className="empty payroll-empty">Add an employee before creating an employee pay profile.</p>}{loadingPayProfile && <p className="payroll-loading">Loading this employee’s pay profile…</p>}{employees.length > 0 && !payProfile && employeeId && !loadingPayProfile && error && <div className="payroll-load-error"><p>The employee pay profile could not be loaded.</p><button type="button" className="secondary-button" onClick={() => setPayProfileRetry((attempt) => attempt + 1)}>Try again</button></div>}{payProfile && employeeId && <form key={employeeId} className="payroll-form employee-payroll-form" onSubmit={savePayProfile}><div className="payroll-employee-summary"><span className="payroll-avatar">{employees.find((employee) => String(employee.id) === employeeId)?.first_name?.[0]}{employees.find((employee) => String(employee.id) === employeeId)?.last_name?.[0]}</span><div><strong>{nameOf(employees.find((employee) => String(employee.id) === employeeId))}</strong><small>{employees.find((employee) => String(employee.id) === employeeId)?.employee_number} · Amounts in {organization.currency_code}</small></div></div><div className="payroll-fields"><label>Base salary<span className="payroll-input-wrap"><small>{organization.currency_code}</small><input type="number" name="base_salary" min="0" step="0.01" required defaultValue={payProfile.base_salary} /></span></label><label>Fixed allowances<span className="payroll-input-wrap"><small>{organization.currency_code}</small><input type="number" name="fixed_allowances" min="0" step="0.01" required defaultValue={payProfile.fixed_allowances} /></span></label><label>Recurring deductions<span className="payroll-input-wrap"><small>{organization.currency_code}</small><input type="number" name="recurring_deductions" min="0" step="0.01" required defaultValue={payProfile.recurring_deductions} /></span></label><label>Pay frequency<select name="pay_frequency" defaultValue={payProfile.pay_frequency || organization.pay_frequency}><option value="monthly">Monthly</option><option value="biweekly">Every two weeks</option><option value="weekly">Weekly</option></select></label><label>Effective from<input type="date" name="effective_from" required defaultValue={payProfile.effective_from?.slice(0, 10) || new Date().toISOString().slice(0, 10)} /></label></div><div className="payroll-form-footer"><p>Changes apply from the effective date and remain restricted to HR admins.</p><button disabled={busy}>{busy ? 'Saving…' : 'Save employee pay details'}</button></div></form>}</section></>
}

function LegacySidebar({ user, admin, page, onNavigate, onLogout, unreadCount }) {
  const canApprove = user.role === 'relief_officer' || user.role === 'line_manager'
  const item = (key, label) => <button className={page === key ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate(key)}>{label}</button>
  return <aside className="sidebar"><div><img className="sidebar-logo" src="/kagia-codes-logo.png" alt="Kagia Codes" /><p className="brand">LeaveHub</p><p className="sidebar-label">Menu</p><nav>{item('dashboard', admin || canApprove ? 'Dashboard' : 'Request leave')}{canApprove && item('request', 'Request leave')}{item('attendance', 'Attendance')}{item('profile', 'My profile')}{item('balances', 'My balance')}{item('calendar', 'Leave calendar')}<button className={page === 'notifications' ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate('notifications')}>Notifications{unreadCount > 0 && <span className="nav-count">{unreadCount}</span>}</button>{admin && item('employees', 'Employees')}{admin && item('reports', 'Reports')}{canApprove && item('inbox', 'Approval inbox')}</nav></div><div className="profile"><strong>{nameOf(user)}</strong><small>{admin ? 'HR administrator' : user.role.replace('_', ' ')}</small><button className="signout" type="button" onClick={onLogout}>Sign out</button></div></aside>
}

function BalanceView({ balances }) {
  return <><section className="page-heading"><p className="eyebrow">Leave entitlement</p><h1>My leave balance</h1><p className="page-summary">Your available leave days for {new Date().getFullYear()} update once a request is fully approved.</p></section><section className="balance-grid">{balances.map((balance) => <article className="balance-card" key={balance.leave_type_id}><p>{balance.leave_type}</p><strong>{balance.remaining_days}<small> days left</small></strong><div className="balance-meter"><span style={{ width: `${Math.min(100, (balance.used_days / balance.entitled_days) * 100)}%` }} /></div><small>{balance.used_days} used of {balance.entitled_days} days</small></article>)}{balances.length === 0 && <p className="empty">Your leave balances will appear here once leave types are configured.</p>}</section></>
}

function ProfileView() {
  const [profile, setProfile] = useState(null); const [error, setError] = useState(''); const [notice, setNotice] = useState('')
  const load = useCallback(() => api('/profile').then((result) => { setProfile(result.data); setError('') }).catch((requestError) => setError(requestError.message)), [])
  useEffect(() => {
    load()
    const refreshWhenVisible = () => { if (document.visibilityState === 'visible') load() }
    const refreshOnFocus = () => load()
    window.addEventListener('focus', refreshOnFocus)
    document.addEventListener('visibilitychange', refreshWhenVisible)
    const refreshTimer = window.setInterval(load, 30000)
    return () => { window.removeEventListener('focus', refreshOnFocus); document.removeEventListener('visibilitychange', refreshWhenVisible); window.clearInterval(refreshTimer) }
  }, [load])
  async function save(event) { event.preventDefault(); setError(''); setNotice(''); try { await api('/profile', { method: 'PATCH', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); setNotice('Date of birth saved.'); await load() } catch (requestError) { setError(requestError.message) } }
  async function changePassword(event) { event.preventDefault(); const form = event.currentTarget; setError(''); setNotice(''); try { await api('/auth/change-password', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) }); form.reset(); setNotice('Your password has been changed.') } catch (requestError) { setError(requestError.message) } }
  async function upload(event) { event.preventDefault(); const form = event.currentTarget; setError(''); setNotice(''); try { const response = await fetch(`${API}/profile/documents`, { method: 'POST', body: new FormData(form) }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message); const documentType = new FormData(form).get('document_type'); form.reset(); setNotice(`${documentType} uploaded.`); await load() } catch (requestError) { setError(requestError.message) } }
  if (!profile) return <main className="loading">Loading profile…</main>
  const documentTypes = ['National ID', 'KRA PIN certificate', 'NSSF/SHA', 'CV', 'Biodata', 'Academic certificate', 'HR manual', 'Contract and JD', 'Company culture']
  return <><section className="page-heading profile-page-heading"><div><p className="eyebrow">Employee self-service</p><h1>My profile</h1><p className="page-summary">Your personal details and important work documents, all in one place.</p></div><span className="profile-page-badge">Profile</span></section>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<section className="profile-layout"><div className="profile-stack"><form className="profile-card" onSubmit={save}><header className="profile-card-heading"><span className="profile-card-icon info-icon">i</span><div><p className="eyebrow">Your information</p><h2>Personal details</h2></div></header><p className="profile-card-description">Keep your employee record current.</p><label>Date of birth<input name="date_of_birth" type="date" defaultValue={profile.date_of_birth || ''} /></label><button>Save changes</button></form><form className="profile-card" onSubmit={changePassword}><header className="profile-card-heading"><span className="profile-card-icon security-icon">✓</span><div><p className="eyebrow">Account security</p><h2>Change password</h2></div></header><p className="profile-card-description">Choose a password of at least 8 characters.</p><label>Current password<input name="current_password" type="password" autoComplete="current-password" required /></label><label>New password<input name="new_password" type="password" minLength="8" autoComplete="new-password" required /></label><button>Update password</button></form></div><section className="profile-card profile-documents"><header className="profile-card-heading"><span className="profile-card-icon upload-icon">↑</span><div><p className="eyebrow">Employee files</p><h2>Upload documents</h2></div></header><p className="profile-card-description">Add a clear copy of each item. Choose the file beside its document type.</p><div className="document-upload-grid">{documentTypes.map((documentType) => <form className="document-upload-form" key={documentType} onSubmit={upload}><input type="hidden" name="document_type" value={documentType} /><label>{documentType}<input aria-label={`${documentType} file`} name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" required /></label><button type="submit">Choose &amp; upload <span aria-hidden="true">↑</span></button></form>)}</div><small className="profile-upload-help">Accepted formats: PDF, JPG or PNG · Up to 30 MB per file</small></section></section><section className="history profile-history"><p className="eyebrow">My records</p><h2>Uploaded documents</h2>{profile.employee_documents?.length ? <div className="document-list">{profile.employee_documents.map((document) => <a className="document-link" key={document.id} href={`${API}/employee-documents/${document.id}/download`} target="_blank" rel="noreferrer"><strong>{document.document_type}</strong><small>{document.original_filename}</small><span className={`document-status ${document.review_status || 'pending'}`}>{(document.review_status || 'pending').replaceAll('_', ' ')}</span>{document.review_notes && <small className="document-review-note">HR note: {document.review_notes}</small>}</a>)}</div> : <p className="empty">No employee documents uploaded yet.</p>}</section></>
}

function EmployeeDocumentReview({ document, onReviewed }) {
  const [status, setStatus] = useState(document.review_status || 'pending')
  const [notes, setNotes] = useState(document.review_notes || '')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const requiresNote = status === 'rejected' || status === 'needs_replacement'

  useEffect(() => {
    setStatus(document.review_status || 'pending')
    setNotes(document.review_notes || '')
  }, [document.id, document.review_status, document.review_notes])

  async function submit(event) {
    event.preventDefault()
    setError(''); setNotice('')
    if (status === 'pending') { setError('Choose a review status before saving.'); return }
    if (requiresNote && !notes.trim()) { setError('Add a note explaining why this document needs attention.'); return }
    setBusy(true)
    try {
      const result = await api(`/employee-documents/${document.id}/review`, { method: 'PATCH', body: JSON.stringify({ review_status: status, review_notes: notes.trim() }) })
      onReviewed(result.data)
      setNotice('Review saved.')
    } catch (requestError) {
      setError(requestError.message)
    } finally {
      setBusy(false)
    }
  }
  return <article className="document-review"><div className="document-review-heading"><a className="document-link" href={`${API}/employee-documents/${document.id}/download`} target="_blank" rel="noreferrer"><strong>{document.document_type}</strong><small>{document.original_filename}</small></a><span className={`document-status ${document.review_status || 'pending'}`}>{(document.review_status || 'pending').replaceAll('_', ' ')}</span></div><form onSubmit={submit}><label>Review status<select value={status} onChange={(event) => { setStatus(event.target.value); setError(''); setNotice('') }}><option value="pending" disabled>Select review status</option><option value="verified">Verified</option><option value="rejected">Rejected</option><option value="needs_replacement">Needs replacement</option></select></label><label>Review note{requiresNote && <span> required</span>}<textarea rows="2" required={requiresNote} value={notes} onChange={(event) => { setNotes(event.target.value); setError(''); setNotice('') }} placeholder={requiresNote ? 'Explain what the employee should correct' : 'Optional note'} /></label>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<button disabled={busy || status === 'pending'}>{busy ? 'Saving...' : 'Save review'}</button></form></article>
}

function EmployeeDetailsOverlay({ employeeId, onClose }) {
  const [employee, setEmployee] = useState(null); const [error, setError] = useState('')
  useEffect(() => {
    api(`/employees/${employeeId}`).then((result) => setEmployee(result.data)).catch((requestError) => setError(requestError.message))
  }, [employeeId])
  if (!employeeId) return null
  return <div className="modal-backdrop" onMouseDown={onClose}><section className="request-modal employee-details-modal" onMouseDown={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={onClose}>×</button>{error ? <p className="alert error">{error}</p> : !employee ? <p className="empty">Loading employee profile…</p> : <><p className="eyebrow">Employee profile</p><h2>{nameOf(employee)}</h2><dl><div><dt>Employee number</dt><dd>{employee.employee_number}</dd></div><div><dt>Work email</dt><dd>{employee.email}</dd></div><div><dt>Date of birth</dt><dd>{employee.date_of_birth ? formatDate(employee.date_of_birth) : 'Not provided'}</dd></div><div><dt>Employment start date</dt><dd>{employee.employment_start_date ? formatDate(employee.employment_start_date) : 'Not provided'}</dd></div><div><dt>Role</dt><dd>{employee.role.replace('_', ' ')}</dd></div><div><dt>Department</dt><dd>{employee.department?.name || 'Unassigned'}</dd></div><div><dt>Line manager</dt><dd>{employee.line_manager ? nameOf(employee.line_manager) : 'Unassigned'}</dd></div><div><dt>Relief officer</dt><dd>{employee.relief_officer ? nameOf(employee.relief_officer) : 'Unassigned'}</dd></div></dl><section className="employee-documents"><p className="eyebrow">Employee documents</p>{employee.employee_documents?.length ? employee.employee_documents.map((document) => <EmployeeDocumentReview document={document} key={document.id} onReviewed={(updated) => setEmployee((current) => ({ ...current, employee_documents: current.employee_documents.map((item) => item.id === updated.id ? updated : item) }))} />) : <p className="empty">No documents uploaded yet.</p>}</section></>}</section></div>
}

function NotificationsView({ items, onRefresh }) {
  async function markRead(id) { await api(`/notifications/${id}/read`, { method: 'PATCH' }); await onRefresh() }
  return <><section className="page-heading"><p className="eyebrow">Updates</p><h1>Notifications</h1><p className="page-summary">Keep track of submitted requests and approval decisions.</p></section><section className="notification-list">{items.length === 0 ? <p className="empty">You are all caught up.</p> : items.map((item) => <button className={item.read_at ? 'notification read' : 'notification'} type="button" key={item.id} onClick={() => !item.read_at && markRead(item.id)}><span><strong>{item.title}</strong><small>{item.message}</small></span><time>{new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(item.created))}</time></button>)}</section></>
}

function CalendarView({ canManageHolidays }) {
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7))
  const [items, setItems] = useState([])
  const [holidays, setHolidays] = useState([])
  const [error, setError] = useState('')
  const [holidayName, setHolidayName] = useState('')
  const [holidayDate, setHolidayDate] = useState('')
  const start = `${month}-01`; const end = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)), 0).toISOString().slice(0, 10)
  const load = useCallback(() => Promise.all([api(`/leave-calendar?start=${start}&end=${end}`), api(`/public-holidays?year=${month.slice(0, 4)}`)]).then(([calendar, holidayList]) => { setItems(calendar.data); setHolidays(holidayList.data) }).catch((requestError) => setError(requestError.message)), [start, end, month])
  useEffect(() => { load() }, [load])
  async function addHoliday(event) { event.preventDefault(); setError(''); try { await api('/public-holidays', { method: 'POST', body: JSON.stringify({ name: holidayName, holiday_date: holidayDate }) }); setHolidayName(''); setHolidayDate(''); await load() } catch (requestError) { setError(requestError.message) } }
  const days = Array.from({ length: Number(end.slice(-2)) }, (_, index) => `${month}-${String(index + 1).padStart(2, '0')}`)
  const entriesFor = (day) => items.filter((item) => item.start_date <= day && item.end_date >= day)
  return <><section className="page-heading calendar-heading"><div><p className="eyebrow">Availability</p><h1>Leave calendar</h1><p className="page-summary">Approved leave and public holidays for the selected month.</p></div><input aria-label="Calendar month" type="month" value={month} onChange={(event) => setMonth(event.target.value)} /></section>{canManageHolidays && <form className="holiday-form" onSubmit={addHoliday}><strong>Add public holiday</strong><input aria-label="Holiday name" placeholder="Holiday name" value={holidayName} onChange={(event) => setHolidayName(event.target.value)} required /><input aria-label="Holiday date" type="date" value={holidayDate} onChange={(event) => setHolidayDate(event.target.value)} required /><button>Add holiday</button></form>}{error && <p className="alert error">{error}</p>}<section className="calendar"><div className="calendar-weekdays">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => <span key={day}>{day}</span>)}</div><div className="calendar-days">{Array.from({ length: (new Date(`${start}T00:00:00`).getDay() + 6) % 7 }, (_, index) => <div className="calendar-day blank" key={`blank-${index}`} />)}{days.map((day) => { const holiday = holidays.find((item) => item.holiday_date === day); return <div className="calendar-day" key={day}><strong>{Number(day.slice(-2))}</strong>{holiday && <span className="holiday">{holiday.name}</span>}{entriesFor(day).map((item) => <span className="calendar-entry" key={item.id}>{nameOf(item.employee)} · {item.leave_type.name}</span>)}</div> })}</div></section></>
}

function LegacyAttendanceView() {
  const [today, setToday] = useState(null); const [history, setHistory] = useState([]); const [note, setNote] = useState(''); const [status, setStatus] = useState('present'); const [error, setError] = useState(''); const [busy, setBusy] = useState(false)
  const load = useCallback(() => Promise.all([api('/attendance/today'), api('/attendance/history')]).then(([todayResult, historyResult]) => { setToday(todayResult.data); setHistory(historyResult.data) }).catch((requestError) => setError(requestError.message)), [])
  useEffect(() => { load() }, [load])
  async function action(path, body = {}) { setBusy(true); setError(''); try { await api(path, { method: 'POST', body: JSON.stringify(body) }); setNote(''); await load() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  const time = (value) => value ? new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '—'
  return <><section className="page-heading"><p className="eyebrow">Workday</p><h1>Attendance</h1><p className="page-summary">Record your workday using the secure server time.</p></section>{error && <p className="alert error">{error}</p>}<section className="attendance-card"><div><p className="eyebrow">Today</p><h2>{today?.clock_in_at ? 'You are clocked in' : 'Ready to begin?'}</h2><p>{today?.clock_in_at ? `Clocked in at ${time(today.clock_in_at)}` : 'Clock in when you start work.'}</p></div>{!today?.clock_in_at ? <div className="attendance-action"><select value={status} onChange={(event) => setStatus(event.target.value)}><option value="present">In office</option><option value="remote">Remote</option></select><input placeholder="Optional note" value={note} onChange={(event) => setNote(event.target.value)} /><button disabled={busy} onClick={() => action('/attendance/clock-in', { status, note })}>Clock in</button></div> : !today.clock_out_at ? <div className="attendance-action"><input placeholder="Optional clock-out note" value={note} onChange={(event) => setNote(event.target.value)} /><button disabled={busy} onClick={() => action('/attendance/clock-out', { note })}>Clock out</button></div> : <strong>Clocked out at {time(today.clock_out_at)} · {Math.floor(today.total_minutes / 60)}h {today.total_minutes % 60}m</strong>}</section><section className="history"><p className="eyebrow">Recent records</p><h2>Attendance history</h2>{history.length ? <div className="table-wrap"><table><thead><tr><th>Date</th><th>Status</th><th>Clock in</th><th>Clock out</th><th>Time worked</th></tr></thead><tbody>{history.map((record) => <tr key={record.id}><td>{formatDate(record.work_date)}</td><td><span className={`status ${record.status}`}>{record.status}</span></td><td>{time(record.clock_in_at)}</td><td>{time(record.clock_out_at)}</td><td>{record.total_minutes ? `${Math.floor(record.total_minutes / 60)}h ${record.total_minutes % 60}m` : '—'}</td></tr>)}</tbody></table></div> : <p className="empty">No attendance records yet.</p>}</section></>
}

function LegacyEmployeePortal({ leaveTypes, requests, onRefresh, user, balances }) {
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [selectedRequest, setSelectedRequest] = useState(null)
  async function submit(event) {
    event.preventDefault(); const form = event.currentTarget; setError(''); setNotice(''); setBusy(true)
    try { const response = await fetch(`${API}/leave-requests`, { method: 'POST', body: new FormData(form) }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'Could not submit request.'); form.reset(); setNotice('Leave request submitted.'); await onRefresh() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }
  const workspace = user.role === 'relief_officer' ? 'Relief officer workspace' : user.role === 'line_manager' ? 'Manager workspace' : 'My leave'
  return <><section className="page-heading" id="dashboard"><p className="eyebrow">{workspace}</p><h1>Request leave</h1>{user.role === 'relief_officer' && <p className="page-summary">Your leave request will be routed to your assigned relief officer and line manager. Use Approval inbox to review requests assigned to you.</p>}</section><section className="balance-strip">{balances.slice(0, 3).map((balance) => <span key={balance.leave_type_id}><strong>{balance.remaining_days}</strong> {balance.leave_type}</span>)}</section><form className="request-card simple-form" onSubmit={submit}><label>Leave type<select name="leave_type_id" required defaultValue=""><option value="" disabled>Select leave type</option>{leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select></label><div className="dates"><label>Start date<input type="date" name="start_date" required /></label><label>End date<input type="date" name="end_date" required /></label></div><label>Handover notes<textarea name="handover_notes" rows="3" required /></label><label>Supporting document<input type="file" name="supporting_document" required /></label>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<button disabled={busy}>{busy ? 'Submitting…' : 'Submit request'}</button></form><section className="history" id="requests"><p className="eyebrow">My requests</p><h2>Leave history</h2><RequestTable requests={requests} onSelect={setSelectedRequest} /></section><RequestDetails request={selectedRequest} onClose={() => setSelectedRequest(null)} /></>
}

function ReportsView({ requests }) {
  const [status, setStatus] = useState('all')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [selectedRequest, setSelectedRequest] = useState(null)
  const visibleRequests = requests.filter((request) => {
    if (status !== 'all' && request.status !== status) return false
    if (startDate && request.end_date < startDate) return false
    if (endDate && request.start_date > endDate) return false
    return true
  })
  const approved = visibleRequests.filter((request) => request.status === 'approved')
  const pending = visibleRequests.filter((request) => request.status.startsWith('pending'))
  const rejected = visibleRequests.filter((request) => request.status === 'rejected')
  const approvedDays = approved.reduce((total, request) => total + Number(request.requested_days || 0), 0)

  function downloadCsv() {
    const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`
    const rows = [
      ['Employee', 'Email', 'Leave type', 'Start date', 'End date', 'Days', 'Status', 'Submitted'],
      ...visibleRequests.map((request) => [nameOf(request.employee), request.employee?.email, request.leave_type?.name, request.start_date, request.end_date, request.requested_days, request.status.replaceAll('_', ' '), request.submitted_at]),
    ]
    const blob = new Blob([rows.map((row) => row.map(quote).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `leave-report-${startDate || 'all'}-to-${endDate || 'all'}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  async function downloadPdf() {
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
    const margin = 15
    const pageWidth = pdf.internal.pageSize.getWidth()
    const pageHeight = pdf.internal.pageSize.getHeight()
    const columns = [
      { label: 'Employee', width: 47, value: (request) => nameOf(request.employee) },
      { label: 'Email', width: 55, value: (request) => request.employee?.email || '—' },
      { label: 'Leave type', width: 35, value: (request) => request.leave_type?.name || '—' },
      { label: 'Dates', width: 45, value: (request) => `${request.start_date} to ${request.end_date}` },
      { label: 'Days', width: 15, value: (request) => String(request.requested_days || '—') },
      { label: 'Status', width: 40, value: (request) => request.status.replaceAll('_', ' ') },
    ]
    let y = 15

    const logo = await new Promise((resolve) => {
      const image = new Image()
      image.onload = () => {
        const canvas = document.createElement('canvas')
        canvas.width = image.naturalWidth || 240
        canvas.height = image.naturalHeight || 80
        canvas.getContext('2d').drawImage(image, 0, 0)
        resolve(canvas.toDataURL('image/png'))
      }
      image.onerror = () => resolve(null)
      image.src = '/kagia-codes-logo.png'
    })

    const drawHeader = () => {
      if (logo) pdf.addImage(logo, 'PNG', margin, y, 31, 12)
      pdf.setTextColor(15, 23, 42)
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(18)
      pdf.text('Leave report', logo ? 52 : margin, y + 6)
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9); pdf.setTextColor(71, 85, 105)
      pdf.text('Kagia Codes · LeaveHub', logo ? 52 : margin, y + 11)
      pdf.text(`Generated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date())}`, pageWidth - margin, y + 6, { align: 'right' })
      y += 20
      pdf.setFillColor(241, 245, 249); pdf.roundedRect(margin, y, pageWidth - margin * 2, 14, 2, 2, 'F')
      pdf.setFontSize(9); pdf.setTextColor(30, 41, 59)
      pdf.text(`Period: ${startDate || 'All dates'} to ${endDate || 'All dates'}   ·   Status: ${status === 'all' ? 'All statuses' : status.replaceAll('_', ' ')}`, margin + 5, y + 6)
      pdf.text(`${visibleRequests.length} requests · ${approvedDays} approved days · ${pending.length} pending · ${rejected.length} rejected`, margin + 5, y + 11)
      y += 20
      pdf.setFillColor(15, 23, 42); pdf.rect(margin, y, pageWidth - margin * 2, 8, 'F')
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(8); pdf.setTextColor(255, 255, 255)
      let x = margin + 2
      columns.forEach((column) => { pdf.text(column.label, x, y + 5); x += column.width })
      y += 8
    }
    const footer = () => {
      pdf.setDrawColor(203, 213, 225); pdf.line(margin, pageHeight - 12, pageWidth - margin, pageHeight - 12)
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8); pdf.setTextColor(100, 116, 139)
      pdf.text('Confidential HR report', margin, pageHeight - 7)
      pdf.text(`Page ${pdf.getNumberOfPages()}`, pageWidth - margin, pageHeight - 7, { align: 'right' })
    }
    const nextPage = () => { footer(); pdf.addPage(); y = 15; drawHeader() }

    drawHeader()
    visibleRequests.forEach((request, rowIndex) => {
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8)
      const cells = columns.map((column) => pdf.splitTextToSize(column.value(request), column.width - 4))
      const rowHeight = Math.max(8, ...cells.map((lines) => lines.length * 4 + 4))
      if (y + rowHeight > pageHeight - 17) nextPage()
      if (rowIndex % 2 === 0) { pdf.setFillColor(248, 250, 252); pdf.rect(margin, y, pageWidth - margin * 2, rowHeight, 'F') }
      pdf.setTextColor(30, 41, 59)
      let x = margin + 2
      cells.forEach((lines, index) => { pdf.text(lines, x, y + 5); x += columns[index].width })
      pdf.setDrawColor(226, 232, 240); pdf.line(margin, y + rowHeight, pageWidth - margin, y + rowHeight)
      y += rowHeight
    })
    footer()
    pdf.save(`leave-report-${startDate || 'all'}-to-${endDate || 'all'}.pdf`)
  }

  return <><section className="page-heading reports-heading"><div><p className="eyebrow">HR reporting</p><h1>Leave reports</h1><p className="page-summary">Filter leave activity, review the totals, and export the results for payroll or leadership reporting.</p></div><div className="report-download-actions"><button type="button" onClick={downloadPdf} disabled={!visibleRequests.length}>Download PDF</button><button type="button" className="secondary-button" onClick={downloadCsv} disabled={!visibleRequests.length}>Download CSV</button></div></section><section className="report-filters"><label>Start date<input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} /></label><label>End date<input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} /></label><label>Status<select value={status} onChange={(event) => setStatus(event.target.value)}><option value="all">All statuses</option><option value="approved">Approved</option><option value="pending_relief_officer">Pending relief officer</option><option value="pending_line_manager">Pending line manager</option><option value="rejected">Rejected</option></select></label><button className="secondary-button" type="button" onClick={() => { setStatus('all'); setStartDate(''); setEndDate('') }}>Clear filters</button></section><section className="admin-stats report-stats"><article className="stat-card"><strong>{visibleRequests.length}</strong><small>Requests in report</small></article><article className="stat-card"><strong>{approvedDays}</strong><small>Approved leave days</small></article><article className="stat-card"><strong>{pending.length}</strong><small>Awaiting approval</small></article><article className="stat-card"><strong>{rejected.length}</strong><small>Rejected requests</small></article></section><section className="history"><div className="section-title"><div><p className="eyebrow">Report preview</p><h2>Leave activity</h2></div><small>{visibleRequests.length} {visibleRequests.length === 1 ? 'record' : 'records'}</small></div><RequestTable requests={visibleRequests} admin onSelect={setSelectedRequest} /></section><RequestDetails request={selectedRequest} onClose={() => setSelectedRequest(null)} /></>
}

function LegacyAdminPortal({ requests, onEmployees }) {
  const pending = requests.filter((request) => request.status.startsWith('pending')).length
  const [filter, setFilter] = useState('all')
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [employeeCount, setEmployeeCount] = useState(0)
  useEffect(() => { api('/employees').then((result) => setEmployeeCount(result.data.length)).catch(() => setEmployeeCount(0)) }, [])
  const visibleRequests = filter === 'pending' ? requests.filter((request) => request.status.startsWith('pending')) : requests
  return <><section className="page-heading" id="dashboard"><p className="eyebrow">HR dashboard</p><h1>Leave overview</h1></section><div className="admin-stats"><button className={filter === 'all' ? 'stat-card active' : 'stat-card'} type="button" onClick={() => setFilter('all')}><strong>{requests.length}</strong><small>Total requests</small></button><button className={filter === 'pending' ? 'stat-card active' : 'stat-card'} type="button" onClick={() => setFilter('pending')}><strong>{pending}</strong><small>Awaiting approval</small></button><button className="stat-card" type="button" onClick={onEmployees}><strong>{employeeCount}</strong><small>Employees</small></button></div><section className="history" id="requests"><p className="eyebrow">All requests</p><h2>{filter === 'pending' ? 'Awaiting approval' : 'Leave activity'}</h2><RequestTable requests={visibleRequests} admin onSelect={setSelectedRequest} /></section><RequestDetails request={selectedRequest} onClose={() => setSelectedRequest(null)} /></>
}

function ApproverDashboard({ user, requests, onOpenInbox }) {
  const [items, setItems] = useState([])
  const [error, setError] = useState('')
  useEffect(() => { api('/approval-inbox').then((result) => setItems(result.data)).catch((requestError) => setError(requestError.message)) }, [])
  const approved = requests.filter((request) => request.status === 'approved').length
  const workspace = user.role === 'relief_officer' ? 'Relief officer workspace' : 'Line manager workspace'
  return <><section className="page-heading"><p className="eyebrow">{workspace}</p><h1>Approval dashboard</h1><p className="page-summary">Review leave requests assigned to you, then submit your own request whenever needed.</p></section>{error && <p className="alert error">{error}</p>}<div className="admin-stats approver-stats"><button className="stat-card active" type="button" onClick={onOpenInbox}><strong>{items.length}</strong><small>Awaiting my decision</small></button><article className="stat-card"><strong>{approved}</strong><small>My approved leave requests</small></article><button className="stat-card" type="button" onClick={onOpenInbox}><strong>Review</strong><small>Open approval inbox</small></button></div><section className="history"><div className="section-title"><div><p className="eyebrow">Priority queue</p><h2>Requests waiting for you</h2></div><button className="secondary-button" type="button" onClick={onOpenInbox}>View all</button></div>{items.length === 0 ? <p className="empty">No requests are waiting for your decision.</p> : <div className="queue-list">{items.slice(0, 4).map((approval) => <button className="queue-item" type="button" key={approval.id} onClick={onOpenInbox}><span><strong>{nameOf(approval.leave_request.employee)}</strong><small>{approval.leave_request.leave_type.name} · {formatDate(approval.leave_request.start_date)} – {formatDate(approval.leave_request.end_date)}</small></span><span className="status pending_relief_officer">Review</span></button>)}</div>}</section></>
}

function ApprovalInbox({ user, onRefresh }) {
  const [items, setItems] = useState([])
  const [error, setError] = useState('')
  const [busyId, setBusyId] = useState(null)
  const [comments, setComments] = useState({})
  const load = useCallback(async () => { const result = await api('/approval-inbox'); setItems(result.data) }, [])
  useEffect(() => { api('/approval-inbox').then((result) => setItems(result.data)).catch((requestError) => setError(requestError.message)) }, [])
  async function decide(approval, decision) {
    setBusyId(approval.id); setError('')
    try { await api(`/leave-requests/${approval.leave_request_id}/decision`, { method: 'POST', body: JSON.stringify({ decision, comments: comments[approval.id] || '' }) }); await Promise.all([load(), onRefresh()]) } catch (requestError) { setError(requestError.message) } finally { setBusyId(null) }
  }
  const isReliefOfficer = user.role === 'relief_officer'
  return <><section className="page-heading approval-heading"><p className="eyebrow">{isReliefOfficer ? 'Relief officer workspace' : 'Manager workspace'}</p><h1>Approval inbox</h1><p className="page-summary">{isReliefOfficer ? 'Review the handover and confirm whether you can provide cover before approving.' : 'Review requests that have been approved by the relief officer.'}</p></section>{error && <p className="alert error">{error}</p>}<section className="inbox-list">{items.length === 0 ? <p className="empty">Nothing is waiting for your approval.</p> : items.map((approval) => { const request = approval.leave_request; return <article className="inbox-card" key={approval.id}><div><p className="eyebrow">{approval.stage.replace('_', ' ')}</p><h2>{nameOf(request.employee)} · {request.leave_type.name}</h2><p>{formatDate(request.start_date)} – {formatDate(request.end_date)} · {request.requested_days} days</p></div><details><summary>View handover notes & document</summary><p>{request.handover_notes}</p>{request.leave_attachments?.map((file) => <a className="document-link" key={file.id} href={`${API}/attachments/${file.id}/download`} target="_blank" rel="noreferrer">View {file.original_filename}</a>)}</details><label>Comment (optional)<textarea rows="2" value={comments[approval.id] || ''} onChange={(event) => setComments({ ...comments, [approval.id]: event.target.value })} /></label><div className="decision-actions"><button disabled={busyId === approval.id} type="button" onClick={() => decide(approval, 'approved')}>Approve</button><button disabled={busyId === approval.id} type="button" className="reject" onClick={() => decide(approval, 'rejected')}>Reject</button></div></article> })}</section></>
}

function EmployeeManagement() {
  const [employees, setEmployees] = useState([])
  const [departments, setDepartments] = useState([])
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [showAddEmployee, setShowAddEmployee] = useState(false)
  const [query, setQuery] = useState('')
  const [departmentFilter, setDepartmentFilter] = useState('all')
  const [roleFilter, setRoleFilter] = useState('all')
  const [activeEmployeeId, setActiveEmployeeId] = useState(null)
  const reliefOfficers = employees.filter((employee) => employee.role === 'relief_officer')
  const lineManagers = employees.filter((employee) => employee.role === 'line_manager')
  const employeeNameById = new Map(employees.map((employee) => [Number(employee.id), nameOf(employee)]))
  const departmentNameById = new Map(departments.map((department) => [Number(department.id), department.name]))
  const load = useCallback(async () => { const [employeeResult, departmentResult] = await Promise.all([api('/employees'), api('/departments')]); setEmployees(employeeResult.data); setDepartments(departmentResult.data) }, [])
  useEffect(() => { Promise.all([api('/employees'), api('/departments')]).then(([employeeResult, departmentResult]) => { setEmployees(employeeResult.data); setDepartments(departmentResult.data) }).catch((requestError) => setError(requestError.message)) }, [])
  async function createEmployee(event) { event.preventDefault(); const form = event.currentTarget; setError(''); setNotice(''); try { await api('/employees', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) }); form.reset(); setNotice('Employee added.'); setShowAddEmployee(false); await load() } catch (requestError) { setError(requestError.message) } }
  async function updateEmployee(event, employeeId) { event.preventDefault(); setError(''); setNotice(''); try { const result = await api(`/employees/${employeeId}`, { method: 'PATCH', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); setEmployees((current) => current.map((employee) => employee.id === employeeId ? result.data : employee)); setNotice('Employee assignment saved and directory updated.'); await load() } catch (requestError) { setError(requestError.message) } }
  async function addDepartment(event) { event.preventDefault(); const form = event.currentTarget; try { await api('/departments', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) }); form.reset(); await load() } catch (requestError) { setError(requestError.message) } }
  const visibleEmployees = employees.filter((employee) => {
    const matchesQuery = `${nameOf(employee)} ${employee.email} ${employee.employee_number || ''}`.toLowerCase().includes(query.trim().toLowerCase())
    return matchesQuery && (departmentFilter === 'all' || Number(employee.department_id) === Number(departmentFilter)) && (roleFilter === 'all' || employee.role === roleFilter)
  })
  const assignedCount = employees.filter((employee) => employee.department_id).length
  const roleName = (role) => role.replaceAll('_', ' ')
  return <><section className="page-heading employee-heading"><div><p className="eyebrow">People operations</p><h1>Employee directory</h1><p className="page-summary">Find people quickly, keep reporting lines current, and make every team member easy to support.</p></div><button type="button" onClick={() => setShowAddEmployee(true)}>+ Add employee</button></section>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<section className="directory-overview"><article><span className="directory-icon">♟</span><div><strong>{employees.length}</strong><small>Team members</small></div></article><article><span className="directory-icon">⌘</span><div><strong>{departments.length}</strong><small>Departments</small></div></article><article><span className="directory-icon">✓</span><div><strong>{assignedCount}</strong><small>Assigned to a team</small></div></article></section><section className="directory-panel"><div className="directory-toolbar"><label className="directory-search"><span>⌕</span><input aria-label="Search employees" placeholder="Search name, email or employee number" value={query} onChange={(event) => setQuery(event.target.value)} /></label><div className="directory-filters"><select aria-label="Filter by department" value={departmentFilter} onChange={(event) => setDepartmentFilter(event.target.value)}><option value="all">All departments</option>{departments.map((department) => <option value={department.id} key={department.id}>{department.name}</option>)}</select><select aria-label="Filter by role" value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)}><option value="all">All roles</option><option value="employee">Employee</option><option value="line_manager">Line manager</option><option value="relief_officer">Relief officer</option><option value="hr_admin">HR admin</option></select></div></div><div className="directory-results"><div><p className="eyebrow">Directory</p><h2>{visibleEmployees.length} {visibleEmployees.length === 1 ? 'person' : 'people'} shown</h2></div>{(query || departmentFilter !== 'all' || roleFilter !== 'all') && <button type="button" className="secondary-button directory-clear" onClick={() => { setQuery(''); setDepartmentFilter('all'); setRoleFilter('all') }}>Clear filters</button>}</div>{visibleEmployees.length === 0 ? <p className="empty directory-empty">No employees match those filters.</p> : <div className="employee-card-grid">{visibleEmployees.map((employee) => <article className="employee-card" key={employee.id}><button className="employee-card-main" type="button" onClick={() => setActiveEmployeeId(employee.id)} aria-label={`View ${nameOf(employee)} profile`}><span className="employee-avatar">{employee.first_name[0]}{employee.last_name[0]}</span><span className="employee-card-name"><strong>{nameOf(employee)}</strong><small>{employee.email}</small></span><span className={`role-badge ${employee.role}`}>{roleName(employee.role)}</span></button><div className="employee-card-meta"><span><small>Department</small><strong>{departmentNameById.get(Number(employee.department_id)) || 'Unassigned'}</strong></span><span><small>Reports to</small><strong>{employeeNameById.get(Number(employee.line_manager_id)) || 'Unassigned'}</strong></span></div><button className="employee-details-link" type="button" onClick={() => setActiveEmployeeId(employee.id)}>View profile <span>→</span></button></article>)}</div>}</section>{showAddEmployee && <section className="management-grid"><form className="request-card simple-form" onSubmit={createEmployee}><div className="form-heading"><h2>Add employee</h2><button type="button" className="secondary-button" onClick={() => setShowAddEmployee(false)}>Cancel</button></div><div className="dates"><label>First name<input name="first_name" required /></label><label>Last name<input name="last_name" required /></label></div><label>Work email<input name="email" type="email" required /></label><label>Temporary password<input name="password" type="password" minLength="8" required /></label><label>Gender<select name="gender" defaultValue="" required><option value="" disabled>Select gender</option><option value="female">Female</option><option value="male">Male</option></select></label><label>Role<select name="role" defaultValue="employee"><option value="employee">Employee</option><option value="relief_officer">Relief officer</option><option value="line_manager">Line manager</option><option value="hr_admin">HR admin</option></select></label><button>Add employee</button></form><form className="request-card simple-form" onSubmit={addDepartment}><h2>Departments</h2><label>New department<input name="name" required /></label><button>Add department</button><div className="department-list">{departments.map((department) => <span key={department.id}>{department.name}</span>)}</div></form></section>}<section className="history assignments-panel"><div className="section-title"><div><p className="eyebrow">Team structure</p><h2>Manage assignments</h2><p>Update reporting lines, cover contacts, and department membership.</p></div></div><div className="employee-list">{employees.map((employee) => <form className="employee-row" key={employee.id} onSubmit={(event) => updateEmployee(event, employee.id)}><strong>{nameOf(employee)}<small>{employee.email}</small></strong><select aria-label={`${nameOf(employee)} gender`} name="gender" defaultValue={employee.gender || ''}><option value="">Gender not set</option><option value="female">Female</option><option value="male">Male</option></select><select aria-label={`${nameOf(employee)} department`} name="department_id" defaultValue={employee.department_id || ''}><option value="">Department</option>{departments.map((department) => <option value={department.id} key={department.id}>{department.name}</option>)}</select><select aria-label={`${nameOf(employee)} relief officer`} name="relief_officer_id" defaultValue={employee.relief_officer_id || ''}><option value="">Relief officer</option>{reliefOfficers.filter((item) => item.id !== employee.id).map((item) => <option value={item.id} key={item.id}>{nameOf(item)}</option>)}</select><select aria-label={`${nameOf(employee)} line manager`} name="line_manager_id" defaultValue={employee.line_manager_id || ''}><option value="">Line manager</option>{lineManagers.filter((item) => item.id !== employee.id).map((item) => <option value={item.id} key={item.id}>{nameOf(item)}</option>)}</select><input type="hidden" name="first_name" value={employee.first_name} /><input type="hidden" name="last_name" value={employee.last_name} /><input type="hidden" name="email" value={employee.email} /><input type="hidden" name="role" value={employee.role} /><button>Save</button></form>)}</div></section>{activeEmployeeId && <EmployeeDetailsOverlay key={activeEmployeeId} employeeId={activeEmployeeId} onClose={() => setActiveEmployeeId(null)} />}</>
}

function LegacyCurrentSidebar({ user, admin, page, onNavigate, onLogout, unreadCount }) {
  const approver = ['relief_officer', 'line_manager'].includes(user.role)
  const item = (key, label) => <button className={page === key ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate(key)}>{label}</button>
  return <aside className="sidebar"><div><img className="sidebar-logo" src="/kagia-codes-logo.png" alt="Kagia Codes" /><p className="brand">LeaveHub</p><p className="sidebar-label">Menu</p><nav>{item('dashboard', admin || approver ? 'Dashboard' : 'Request leave')}{approver && item('request', 'Request leave')}{item('attendance', 'Attendance')}{item('profile', 'My profile')}{item('balances', 'My balance')}{item('calendar', 'Leave calendar')}<button className={page === 'notifications' ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate('notifications')}>Notifications{unreadCount > 0 && <span className="nav-count">{unreadCount}</span>}</button>{admin && item('employees', 'Employees')}{admin && item('reports', 'Reports')}{approver && item('delegations', 'Delegations')}{approver && item('inbox', 'Approval inbox')}</nav></div><div className="profile"><strong>{nameOf(user)}</strong><small>{admin ? 'HR administrator' : user.role.replace('_', ' ')}</small><button className="signout" type="button" onClick={onLogout}>Sign out</button></div></aside>
}

function RequestDetails({ request, onClose, admin = false }) {
  const [reason, setReason] = useState(''); const [overrideStatus, setOverrideStatus] = useState('approved'); const [audits, setAudits] = useState([]); const [error, setError] = useState(''); const [busy, setBusy] = useState(false)
  useEffect(() => { if (admin && request) api(`/leave-requests/${request.id}/audits`).then((result) => setAudits(result.data)).catch((requestError) => setError(requestError.message)) }, [admin, request])
  if (!request) return null
  const canCancel = !admin && ['pending_relief_officer', 'pending_line_manager', 'approved'].includes(request.status)
  async function submit(path, body) { setBusy(true); setError(''); try { await api(path, { method: 'POST', body: JSON.stringify(body) }); window.dispatchEvent(new Event('leave-request-updated')); onClose() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  return <div className="modal-backdrop" onMouseDown={onClose}><section className="request-modal employee-details-modal" onMouseDown={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={onClose}>×</button><p className="eyebrow">Leave request</p><h2>{request.leave_type?.name}</h2><dl><div><dt>Dates</dt><dd>{formatDate(request.start_date)} – {formatDate(request.end_date)}</dd></div><div><dt>Days requested</dt><dd>{request.requested_days}</dd></div><div><dt>Status</dt><dd><span className={`status ${request.status}`}>{request.status.replaceAll('_', ' ')}</span></dd></div>{request.reason && <div><dt>Reason</dt><dd>{request.reason}</dd></div>}<div><dt>Handover notes</dt><dd>{request.handover_notes}</dd></div></dl>{(canCancel || admin) && <section className="cancellation-panel"><strong>{admin ? 'HR override' : 'Need to withdraw this request?'}</strong>{admin && <label>Set status<select value={overrideStatus} onChange={(event) => setOverrideStatus(event.target.value)}><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select></label>}<label>{admin ? 'Audit reason' : 'Cancellation reason'}<textarea rows="2" value={reason} onChange={(event) => setReason(event.target.value)} required /></label>{error && <p className="alert error">{error}</p>}<button type="button" className="reject" disabled={busy || !reason.trim()} onClick={() => submit(admin ? `/leave-requests/${request.id}/override` : `/leave-requests/${request.id}/cancel`, admin ? { status: overrideStatus, reason } : { reason })}>{busy ? 'Saving…' : admin ? 'Save HR override' : 'Cancel leave request'}</button></section>}{admin && <section className="audit-history"><p className="eyebrow">Audit history</p>{audits.length ? audits.map((audit) => <p key={audit.id}><strong>{audit.action.replaceAll('_', ' ')}</strong> · {audit.actor ? nameOf(audit.actor) : 'HR'}<small>{audit.reason}</small></p>) : <p className="empty">No overrides or cancellations recorded.</p>}</section>}</section></div>
}

function LeaveAssistant() {
  const [messages, setMessages] = useState([{ role: 'assistant', text: 'Hi — I’m the LeaveHub guide. I can help you understand your balance, choose a leave type, and prepare your request.' }]); const [question, setQuestion] = useState(''); const [busy, setBusy] = useState(false); const [error, setError] = useState('')
  async function ask(message = question) { const text = message.trim(); if (!text || busy) return; setMessages((current) => [...current, { role: 'user', text }]); setQuestion(''); setBusy(true); setError(''); try { const result = await api('/leave-assistant', { method: 'POST', body: JSON.stringify({ message: text }) }); setMessages((current) => [...current, { role: 'assistant', text: result.data.answer }]) } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  return <aside className="leave-assistant"><div className="leave-assistant-heading"><img className="assistant-logo" src="/kagia-codes-logo.png" alt="Kagia Codes" /><div><p className="eyebrow">Kagia Codes · LeaveHub</p><h2>Leave Assistant</h2><small><i /> Online to help with your request</small></div></div><div className="assistant-messages" aria-live="polite">{messages.map((message, index) => <div className={`assistant-message ${message.role}`} key={index}><span>{message.role === 'assistant' ? 'LH' : 'You'}</span><p>{message.text}</p></div>)}{busy && <div className="assistant-message assistant"><span>LH</span><p className="assistant-thinking"><i /><i /><i /></p></div>}</div>{error && <p className="assistant-error">{error}</p>}<div className="assistant-prompts"><p>How can I help?</p><button type="button" onClick={() => ask('Show me how to choose the right leave type.')}>Choose leave type</button><button type="button" onClick={() => ask('What leave balance is available to me?')}>My leave balance</button><button type="button" onClick={() => ask('What should I include before submitting a leave request?')}>Prepare my request</button></div><form className="assistant-input" onSubmit={(event) => { event.preventDefault(); ask() }}><input value={question} onChange={(event) => setQuestion(event.target.value)} maxLength="1500" placeholder="Type your leave question…" aria-label="Ask the Leave Assistant" /><button aria-label="Send question" disabled={busy || !question.trim()}>↑</button></form><small className="assistant-disclaimer">LeaveHub offers guidance; your HR team makes final policy and approval decisions.</small></aside>
}

function EmployeePortal({ leaveTypes, requests, onRefresh, user, balances }) {
  const [error, setError] = useState(''); const [notice, setNotice] = useState(''); const [busy, setBusy] = useState(false); const [selectedRequest, setSelectedRequest] = useState(null)
  async function submit(event) { event.preventDefault(); const form = event.currentTarget; setBusy(true); setError(''); try { const response = await fetch(`${API}/leave-requests`, { method: 'POST', body: new FormData(form) }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'Could not submit request.'); form.reset(); setNotice('Leave request submitted.'); await onRefresh() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  return <><section className="page-heading"><p className="eyebrow">My leave</p><h1>Request leave</h1></section><section className="balance-strip">{balances.slice(0, 3).map((balance) => <span key={balance.leave_type_id}><strong>{balance.remaining_days}</strong> {balance.leave_type}</span>)}</section><section className="leave-request-workspace"><form className="request-card simple-form" onSubmit={submit}><label>Leave type<select name="leave_type_id" required defaultValue=""><option value="" disabled>Select leave type</option>{leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select></label><label>Duration<select name="duration" defaultValue="full_day"><option value="full_day">Full day</option><option value="half_day">Half day</option></select></label><div className="dates"><label>Start date<input type="date" name="start_date" required /></label><label>End date<input type="date" name="end_date" required /></label></div><label>Handover notes<textarea name="handover_notes" rows="3" required /></label><label>Supporting document<input type="file" name="supporting_document" required /></label>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<button disabled={busy}>{busy ? 'Submitting…' : 'Submit request'}</button></form><LeaveAssistant /></section><section className="history"><p className="eyebrow">My requests</p><h2>Leave history</h2><RequestTable requests={requests} onSelect={setSelectedRequest} /></section><RequestDetails request={selectedRequest} onClose={() => setSelectedRequest(null)} /></>
}

function AdminPortal({ requests, onEmployees }) {
  const [selectedRequest, setSelectedRequest] = useState(null); const pending = requests.filter((request) => request.status.startsWith('pending')).length
  return <><section className="page-heading"><p className="eyebrow">HR dashboard</p><h1>Leave overview</h1></section><div className="admin-stats"><article className="stat-card"><strong>{requests.length}</strong><small>Total requests</small></article><article className="stat-card"><strong>{pending}</strong><small>Awaiting approval</small></article><button className="stat-card" type="button" onClick={onEmployees}><strong>People</strong><small>Manage employees</small></button></div><section className="history"><p className="eyebrow">All requests</p><h2>Leave activity</h2><RequestTable requests={requests} admin onSelect={setSelectedRequest} /></section><RequestDetails request={selectedRequest} admin onClose={() => setSelectedRequest(null)} /></>
}

function DelegationsView() {
  const [items, setItems] = useState([]); const [employees, setEmployees] = useState([]); const [error, setError] = useState(''); const load = useCallback(() => Promise.all([api('/approval-delegations'), api('/approval-delegations/candidates')]).then(([delegations, people]) => { setItems(delegations.data); setEmployees(people.data) }).catch((requestError) => setError(requestError.message)), [])
  useEffect(() => { load() }, [load]); async function save(event) { event.preventDefault(); try { await api('/approval-delegations', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(event.currentTarget))) }); event.currentTarget.reset(); await load() } catch (requestError) { setError(requestError.message) } }
  return <><section className="page-heading"><p className="eyebrow">Approvals</p><h1>Delegate approvals</h1><p className="page-summary">Temporarily route new approval tasks to a colleague while you are unavailable.</p></section>{error && <p className="alert error">{error}</p>}<form className="request-card simple-form" onSubmit={save}><label>Delegate to<select name="delegate_id" required defaultValue=""><option value="" disabled>Select colleague</option>{employees.map((employee) => <option value={employee.id} key={employee.id}>{nameOf(employee)}</option>)}</select></label><div className="dates"><label>Starts on<input name="starts_on" type="date" required /></label><label>Ends on<input name="ends_on" type="date" required /></label></div><button>Save delegation</button></form><section className="history"><h2>Your delegations</h2>{items.length ? items.map((item) => <div className="delegation-row" key={item.id}><span><strong>{item.delegate ? nameOf(item.delegate) : 'Delegate'}</strong><small>{formatDate(item.starts_on)} – {formatDate(item.ends_on)}</small></span><button className="secondary-button" type="button" onClick={async () => { await api(`/approval-delegations/${item.id}`, { method: 'DELETE' }); await load() }}>Remove</button></div>) : <p className="empty">No approval delegations set.</p>}</section></>
}

function AttendanceCorrectionModal({ record, onClose, onSaved }) {
  const [status, setStatus] = useState(record.status); const [clockIn, setClockIn] = useState(datetimeLocal(record.clock_in_at)); const [clockOut, setClockOut] = useState(datetimeLocal(record.clock_out_at)); const [clockInNote, setClockInNote] = useState(record.clock_in_note || ''); const [clockOutNote, setClockOutNote] = useState(record.clock_out_note || ''); const [reason, setReason] = useState(''); const [error, setError] = useState(''); const [busy, setBusy] = useState(false)
  async function submit(event) { event.preventDefault(); setBusy(true); setError(''); try { const result = await api(`/attendance/${record.id}`, { method: 'PATCH', body: JSON.stringify({ status, clock_in_at: clockIn || null, clock_out_at: clockOut || null, clock_in_note: clockInNote, clock_out_note: clockOutNote, reason }) }); await onSaved(result.message); onClose() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  return <div className="modal-backdrop" onMouseDown={onClose}><form className="request-modal attendance-correction-modal simple-form" onSubmit={submit} onMouseDown={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={onClose}>×</button><p className="eyebrow">HR attendance correction</p><h2>{nameOf(record.employee)} · {formatDate(record.work_date)}</h2><p className="page-summary">Update the recorded facts. Every change is retained in the audit log.</p><label>Status<select value={status} onChange={(event) => setStatus(event.target.value)}><option value="present">Present</option><option value="remote">Remote</option><option value="late">Late</option><option value="absent">Absent</option><option value="on_leave">On leave</option></select></label><div className="dates"><label>Clock in<input type="datetime-local" value={clockIn} onChange={(event) => setClockIn(event.target.value)} /></label><label>Clock out<input type="datetime-local" value={clockOut} onChange={(event) => setClockOut(event.target.value)} /></label></div><label>Clock-in note<input value={clockInNote} onChange={(event) => setClockInNote(event.target.value)} /></label><label>Clock-out note<input value={clockOutNote} onChange={(event) => setClockOutNote(event.target.value)} /></label><label>Correction reason<textarea rows="3" value={reason} onChange={(event) => setReason(event.target.value)} required placeholder="Explain what was corrected and why" /></label>{error && <p className="alert error">{error}</p>}<button disabled={busy || !reason.trim()}>{busy ? 'Saving…' : 'Save correction'}</button></form></div>
}

function datetimeLocal(value) { if (!value) return ''; const date = new Date(value); const pad = (number) => String(number).padStart(2, '0'); return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}` }

function AttendanceView({ admin }) {
  const [records, setRecords] = useState([]); const [today, setToday] = useState(null); const [status, setStatus] = useState('present'); const [note, setNote] = useState(''); const [error, setError] = useState(''); const [notice, setNotice] = useState(''); const [busy, setBusy] = useState(false); const [now, setNow] = useState(new Date()); const [correcting, setCorrecting] = useState(null)
  const load = useCallback(() => Promise.all([admin ? api('/attendance/report') : api('/attendance/history'), api('/attendance/today')]).then(([list, day]) => { setRecords(list.data); setToday(day.data) }).catch((requestError) => setError(requestError.message)), [admin])
  useEffect(() => { load(); const timer = window.setInterval(() => setNow(new Date()), 60000); return () => window.clearInterval(timer) }, [load])
  const time = (value) => value ? new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '—'
  const workedMinutes = today?.clock_in_at ? (today.clock_out_at ? Number(today.total_minutes) : Math.max(0, Math.floor((now.getTime() - new Date(today.clock_in_at).getTime()) / 60000))) : 0
  const duration = `${Math.floor(workedMinutes / 60)}h ${workedMinutes % 60}m`
  async function action(path) { setBusy(true); setError(''); setNotice(''); try { const result = await api(path, { method: 'POST', body: JSON.stringify(path.endsWith('clock-in') ? { status, note } : { note }) }); setNote(''); setNotice(result.message || 'Attendance updated.'); await load() } catch (requestError) { setError(requestError.message) } finally { setBusy(false) } }
  function exportCsv() { const rows = [['Employee', 'Date', 'Status', 'Clock in', 'Clock out', 'Minutes'], ...records.map((r) => [r.employee ? nameOf(r.employee) : '', r.work_date, r.status, r.clock_in_at || '', r.clock_out_at || '', r.total_minutes])]; const url = URL.createObjectURL(new Blob([rows.map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' })); const link = document.createElement('a'); link.href = url; link.download = `attendance-report-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url) }
  function exportPdf() {
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
    const margin = 12; const pageWidth = pdf.internal.pageSize.getWidth(); const pageHeight = pdf.internal.pageSize.getHeight()
    const columns = [
      { label: 'Employee', width: 62, value: (record) => nameOf(record.employee) },
      { label: 'Date', width: 37, value: (record) => formatDate(record.work_date) },
      { label: 'Status', width: 39, value: (record) => record.status.replaceAll('_', ' ') },
      { label: 'Clock in', width: 36, value: (record) => time(record.clock_in_at) },
      { label: 'Clock out', width: 36, value: (record) => time(record.clock_out_at) },
      { label: 'Time worked', width: 40, value: (record) => record.total_minutes ? `${Math.floor(record.total_minutes / 60)}h ${record.total_minutes % 60}m` : '—' },
    ]
    let y = 26
    const drawHeader = () => {
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(16); pdf.setTextColor(15, 23, 42); pdf.text('Team attendance register', margin, 15)
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(9); pdf.setTextColor(71, 85, 105)
      pdf.text(`Generated ${new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date())} · ${records.length} records`, margin, 21)
      pdf.setFillColor(15, 23, 42); pdf.rect(margin, y, pageWidth - margin * 2, 8, 'F')
      pdf.setFont('helvetica', 'bold'); pdf.setFontSize(8); pdf.setTextColor(255, 255, 255)
      let x = margin + 2
      columns.forEach((column) => { pdf.text(column.label, x, y + 5); x += column.width })
      y += 8
    }
    const nextPage = () => { pdf.addPage(); y = 26; drawHeader() }
    drawHeader()
    records.forEach((record, rowIndex) => {
      pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8)
      const cells = columns.map((column) => pdf.splitTextToSize(column.value(record), column.width - 4))
      const rowHeight = Math.max(8, ...cells.map((lines) => lines.length * 4 + 4))
      if (y + rowHeight > pageHeight - 15) nextPage()
      if (rowIndex % 2 === 0) { pdf.setFillColor(248, 250, 252); pdf.rect(margin, y, pageWidth - margin * 2, rowHeight, 'F') }
      pdf.setTextColor(30, 41, 59)
      let x = margin + 2
      cells.forEach((lines, index) => { pdf.text(lines, x, y + 5); x += columns[index].width })
      pdf.setDrawColor(226, 232, 240); pdf.line(margin, y + rowHeight, pageWidth - margin, y + rowHeight)
      y += rowHeight
    })
    for (let page = 1; page <= pdf.getNumberOfPages(); page += 1) {
      pdf.setPage(page); pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8); pdf.setTextColor(100, 116, 139)
      pdf.text('Confidential HR report', margin, pageHeight - 7)
      pdf.text(`Page ${page} of ${pdf.getNumberOfPages()}`, pageWidth - margin, pageHeight - 7, { align: 'right' })
    }
    pdf.save(`team-attendance-${new Date().toISOString().slice(0, 10)}.pdf`)
  }
  const state = !today?.clock_in_at ? 'not-started' : today.clock_out_at ? 'complete' : 'active'
  return <><section className="page-heading reports-heading"><div><p className="eyebrow">{admin ? 'HR attendance' : 'My workday'}</p><h1>{admin ? 'Attendance register' : 'Attendance'}</h1><p className="page-summary">{new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).format(now)} · all times use the LeaveHub server record.</p></div>{admin && <div className="report-download-actions"><button type="button" onClick={exportPdf} disabled={!records.length}>Download PDF</button><button className="secondary-button" type="button" onClick={exportCsv} disabled={!records.length}>Download CSV</button></div>}</section>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}<section className={`attendance-timecard ${state}`}><div className="attendance-timecard-copy"><span className="attendance-state-dot" /><p className="eyebrow">{state === 'not-started' ? 'Not clocked in' : state === 'active' ? 'Workday in progress' : 'Workday complete'}</p><h2>{state === 'not-started' ? 'Start your workday' : state === 'active' ? 'You are clocked in' : 'You have clocked out'}</h2><p>{state === 'not-started' ? 'Choose where you are working, then record your start time.' : state === 'active' ? `Started at ${time(today.clock_in_at)}. Your elapsed time updates automatically.` : `Recorded ${duration} today.`}</p></div><div className="attendance-time"><strong>{state === 'not-started' ? '—' : duration}</strong><small>{state === 'active' ? 'elapsed time' : 'time worked'}</small></div></section><section className="attendance-actions-panel">{state === 'not-started' ? <><div className="attendance-location"><button type="button" className={status === 'present' ? 'selected' : ''} onClick={() => setStatus('present')}><span>⌂</span>In office</button><button type="button" className={status === 'remote' ? 'selected' : ''} onClick={() => setStatus('remote')}><span>⌁</span>Remote</button></div><label>Clock-in note <small>Optional</small><input value={note} placeholder="For example, visiting a client this morning" onChange={(event) => setNote(event.target.value)} /></label><button className="attendance-primary" type="button" disabled={busy} onClick={() => action('/attendance/clock-in')}>{busy ? 'Recording…' : 'Clock in'}</button></> : state === 'active' ? <><div className="attendance-checks"><span><small>Clocked in</small><strong>{time(today.clock_in_at)}</strong></span><span><small>Location</small><strong>{today.status === 'remote' ? 'Remote' : 'In office'}</strong></span></div><label>Clock-out note <small>Optional</small><input value={note} placeholder="Add a note before you finish" onChange={(event) => setNote(event.target.value)} /></label><button className="attendance-primary clock-out" type="button" disabled={busy} onClick={() => action('/attendance/clock-out')}>{busy ? 'Recording…' : 'Clock out'}</button></> : <div className="attendance-complete"><span>✓</span><div><strong>Attendance recorded</strong><small>Clocked in {time(today.clock_in_at)} · clocked out {time(today.clock_out_at)}</small></div></div>}</section><section className="history attendance-history"><div className="section-title"><div><p className="eyebrow">{admin ? 'Team register' : 'Recent activity'}</p><h2>{admin ? 'Team attendance' : 'Attendance history'}</h2></div><small>{records.length} {records.length === 1 ? 'record' : 'records'}</small></div>{records.length ? <div className="table-wrap"><table><thead><tr>{admin && <th>Employee</th>}<th>Date</th><th>Status</th><th>Clock in</th><th>Clock out</th><th>Time worked</th>{admin && <th aria-label="Actions" />}</tr></thead><tbody>{records.map((record) => <tr key={record.id}>{admin && <td>{nameOf(record.employee)}</td>}<td>{formatDate(record.work_date)}</td><td><span className={`status ${record.status}`}>{record.status.replaceAll('_', ' ')}</span></td><td>{time(record.clock_in_at)}</td><td>{time(record.clock_out_at)}</td><td>{record.total_minutes ? `${Math.floor(record.total_minutes / 60)}h ${record.total_minutes % 60}m` : '—'}</td>{admin && <td><button className="secondary-button attendance-review" type="button" onClick={() => setCorrecting(record)}>Correct</button></td>}</tr>)}</tbody></table></div> : <p className="empty">No attendance records yet.</p>}</section>{correcting && <AttendanceCorrectionModal record={correcting} onClose={() => setCorrecting(null)} onSaved={async (message) => { setNotice(message); await load() }} />}</>
}

function AuditLogView() {
  const [items, setItems] = useState([]); const [error, setError] = useState(''); const [area, setArea] = useState('all')
  useEffect(() => { api('/audit-log').then((result) => setItems(result.data)).catch((requestError) => setError(requestError.message)) }, [])
  const visible = area === 'all' ? items : items.filter((item) => item.area === area)
  const dateTime = (value) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
  return <><section className="page-heading"><p className="eyebrow">HR controls</p><h1>Audit log</h1><p className="page-summary">A permanent record of HR attendance corrections, leave cancellations, and overrides.</p></section><div className="audit-filters"><button type="button" className={area === 'all' ? 'active' : ''} onClick={() => setArea('all')}>All activity</button><button type="button" className={area === 'attendance' ? 'active' : ''} onClick={() => setArea('attendance')}>Attendance</button><button type="button" className={area === 'leave' ? 'active' : ''} onClick={() => setArea('leave')}>Leave</button></div>{error && <p className="alert error">{error}</p>}<section className="audit-log">{visible.length ? visible.map((item) => <article key={item.id} className="audit-entry"><span className={`audit-area ${item.area}`}>{item.area}</span><div><strong>{item.action.replaceAll('_', ' ')}</strong><p>{item.subject ? nameOf(item.subject) : 'Employee record'}{item.work_date && ` · ${formatDate(item.work_date)}`}{item.leave_type && ` · ${item.leave_type}`}</p><small>{item.reason || 'No reason recorded'}</small></div><aside><strong>{item.actor ? nameOf(item.actor) : 'System'}</strong><time>{dateTime(item.created)}</time></aside></article>) : <p className="empty">No audit activity matches this filter.</p>}</section></>
}

function TeamAvailabilityView() {
  const [items, setItems] = useState([]); const [date, setDate] = useState(new Date().toISOString().slice(0, 10)); const [error, setError] = useState('')
  const load = useCallback(() => api(`/team-availability?date=${date}`).then((result) => setItems(result.data)).catch((requestError) => setError(requestError.message)), [date])
  useEffect(() => { load() }, [load])
  const groups = ['available', 'present', 'remote', 'late', 'absent', 'on_leave']
  const label = (status) => ({ available: 'Available', present: 'In office', remote: 'Remote', late: 'Late', absent: 'Absent', on_leave: 'On leave' }[status] || status)
  return <><section className="page-heading availability-heading"><div><p className="eyebrow">Team workspace</p><h1>Team availability</h1><p className="page-summary">See where your team is today and plan coverage with confidence.</p></div><label>View date<input type="date" value={date} onChange={(event) => setDate(event.target.value)} /></label></section>{error && <p className="alert error">{error}</p>}<section className="availability-summary">{groups.map((status) => <article key={status}><strong>{items.filter((item) => item.status === status).length}</strong><small>{label(status)}</small></article>)}</section><section className="availability-board">{groups.map((status) => { const people = items.filter((item) => item.status === status); return people.length ? <article className={`availability-column ${status}`} key={status}><header><span className={`status ${status}`}>{label(status)}</span><small>{people.length}</small></header>{people.map((item) => <div className="availability-person" key={item.employee.id}><span>{item.employee.first_name[0]}{item.employee.last_name[0]}</span><div><strong>{nameOf(item.employee)}</strong><small>{item.employee.department?.name || 'No department'}{item.leave_type && ` · ${item.leave_type}`}</small></div></div>)}</article> : null })}</section>{!items.length && !error && <p className="empty">No active team members are assigned to your view.</p>}</>
}

function EmployeeLifecycleView() {
  const [employees, setEmployees] = useState([]); const [selectedId, setSelectedId] = useState(''); const [lifecycle, setLifecycle] = useState(null); const [error, setError] = useState(''); const [notice, setNotice] = useState(''); const [offboard, setOffboard] = useState(false)
  useEffect(() => { api('/employees').then((result) => { setEmployees(result.data); if (result.data[0]) setSelectedId(String(result.data[0].id)) }).catch((requestError) => setError(requestError.message)) }, [])
  const load = useCallback(() => { if (selectedId) api(`/employees/${selectedId}/lifecycle`).then((result) => setLifecycle(result.data)).catch((requestError) => setError(requestError.message)) }, [selectedId])
  useEffect(() => { load() }, [load])
  const done = (phase, key) => Boolean(lifecycle?.tasks.find((task) => task.phase === phase && task.task_key === key && task.completed_at))
  async function toggle(phase, taskKey, completed) { setError(''); try { const result = await api(`/employees/${selectedId}/lifecycle/task`, { method: 'PATCH', body: JSON.stringify({ phase, task_key: taskKey, completed }) }); setLifecycle(result.data) } catch (requestError) { setError(requestError.message) } }
  async function submitOffboarding(event) { event.preventDefault(); setError(''); try { const form = Object.fromEntries(new FormData(event.currentTarget)); const result = await api(`/employees/${selectedId}/offboard`, { method: 'POST', body: JSON.stringify(form) }); setLifecycle(result.data); setEmployees((current) => current.map((employee) => employee.id === Number(selectedId) ? { ...employee, is_active: false } : employee)); setNotice(result.message); setOffboard(false) } catch (requestError) { setError(requestError.message) } }
  const taskList = (phase, title, tasks) => <section className="lifecycle-checklist"><div className="section-title"><div><p className="eyebrow">{phase}</p><h2>{title}</h2></div><small>{Object.keys(tasks).filter((key) => done(phase, key)).length}/{Object.keys(tasks).length} complete</small></div>{Object.entries(tasks).map(([key, title]) => <label className="lifecycle-task" key={key}><input type="checkbox" checked={done(phase, key)} onChange={(event) => toggle(phase, key, event.target.checked)} /><span><strong>{title}</strong>{phase === 'onboarding' && key === 'documents' && <small>{lifecycle.employee.employee_documents?.length || 0} document(s) uploaded</small>}</span></label>)}</section>
  return <><section className="page-heading lifecycle-heading"><div><p className="eyebrow">People operations</p><h1>Employee lifecycle</h1><p className="page-summary">Guide onboarding and offboarding with accountable steps, documents, and final leave information.</p></div><select aria-label="Select employee" value={selectedId} onChange={(event) => { setSelectedId(event.target.value); setLifecycle(null); setOffboard(false) }}>{employees.map((employee) => <option value={employee.id} key={employee.id}>{nameOf(employee)}{employee.is_active ? '' : ' · inactive'}</option>)}</select></section>{error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}{lifecycle && <><section className="lifecycle-profile"><div><span className="employee-avatar">{lifecycle.employee.first_name[0]}{lifecycle.employee.last_name[0]}</span><div><h2>{nameOf(lifecycle.employee)}</h2><p>{lifecycle.employee.department?.name || 'No department'} · Started {lifecycle.employee.employment_start_date ? formatDate(lifecycle.employee.employment_start_date) : 'not recorded'}</p></div></div><span className={`status ${lifecycle.employee.is_active ? 'approved' : 'rejected'}`}>{lifecycle.employee.is_active ? 'Active' : 'Inactive'}</span></section><section className="lifecycle-grid">{taskList('onboarding', 'Onboarding checklist', lifecycle.onboarding_tasks)}{taskList('offboarding', 'Offboarding checklist', lifecycle.offboarding_tasks)}<section className="lifecycle-checklist"><p className="eyebrow">Leave handover</p><h2>Final leave balance</h2>{lifecycle.leave_balances.length ? <div className="final-balances">{lifecycle.leave_balances.map((balance) => <span key={balance.id}><strong>{balance.remaining_days}</strong><small>{balance.leave_type?.name || 'Leave'} days remaining</small></span>)}</div> : <p className="empty">No leave balances recorded for this year.</p>}</section></section>{lifecycle.employee.is_active && <section className="offboarding-panel"><div><p className="eyebrow">Account control</p><h2>Offboard employee</h2><p>Deactivating the account immediately prevents sign-in and new requests. Existing HR records remain available.</p></div>{!offboard ? <button className="reject" type="button" onClick={() => setOffboard(true)}>Start offboarding</button> : <form onSubmit={submitOffboarding}><label>Last working date<input required name="offboarding_date" type="date" /></label><label>Reason<textarea required name="reason" rows="2" placeholder="For example, resignation or contract completion" /></label><div><button className="secondary-button" type="button" onClick={() => setOffboard(false)}>Cancel</button><button className="reject">Deactivate account</button></div></form>}</section>}</>}</>
}

function Sidebar({ user, admin, page, onNavigate, onLogout, unreadCount }) {
  const approver = ['relief_officer', 'line_manager'].includes(user.role)
  const item = (key, label) => <button className={page === key ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate(key)}>{label}</button>
  return <aside className="sidebar"><div><img className="sidebar-logo" src="/kagia-codes-logo.png" alt="Kagia Codes" /><p className="brand">LeaveHub</p><p className="sidebar-label">Menu</p><nav>{item('dashboard', admin || approver ? 'Dashboard' : 'Request leave')}{(admin || approver) && item('request', 'Request leave')}{(admin || approver) && item('availability', 'Team availability')}{item('attendance', 'Attendance')}{item('profile', 'My profile')}{item('balances', 'My balance')}{item('calendar', 'Leave calendar')}<button className={page === 'notifications' ? 'nav-item active' : 'nav-item'} type="button" onClick={() => onNavigate('notifications')}>Notifications{unreadCount > 0 && <span className="nav-count">{unreadCount}</span>}</button>{admin && item('employees', 'Employees')}{admin && item('payroll-setup', 'Organization & payroll')}{admin && item('lifecycle', 'Employee lifecycle')}{admin && item('reports', 'Reports')}{admin && item('audit-log', 'Audit log')}{approver && item('delegations', 'Delegations')}{approver && item('inbox', 'Approval inbox')}</nav></div><div className="profile"><strong>{nameOf(user)}</strong><small>{admin ? 'Organization administrator' : user.role.replace('_', ' ')}</small><button className="signout" type="button" onClick={onLogout}>Sign out</button></div></aside>
}

export default function App() {
  const [user, setUser] = useState(null)
  const [leaveTypes, setLeaveTypes] = useState([])
  const [requests, setRequests] = useState([])
  const [balances, setBalances] = useState([])
  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState('dashboard')
  const refresh = useCallback(async () => { const [types, requestList, balanceList, notificationList] = await Promise.all([api('/leave-types'), api('/leave-requests'), api('/leave-balances'), api('/notifications')]); setLeaveTypes(types.data); setRequests(requestList.data); setBalances(balanceList.data); setNotifications(notificationList.data) }, [])
  useEffect(() => { const update = () => { refresh() }; window.addEventListener('leave-request-updated', update); return () => window.removeEventListener('leave-request-updated', update) }, [refresh])
  useEffect(() => { api('/auth/me').then(async ({ data }) => { setUser(data); await refresh() }).catch(() => setUser(null)).finally(() => setLoading(false)) }, [refresh])
  async function logout() { await api('/auth/logout', { method: 'POST' }); setUser(null); setRequests([]); setBalances([]); setNotifications([]); setPage('dashboard') }
  async function authenticated(account) { setUser(account); setPage('dashboard'); await refresh() }
  if (loading) return <main className="loading">Loading LeaveHub…</main>
  if (!user) return <AuthScreen onAuthenticated={authenticated} />
  const admin = user.role === 'hr_admin'
  const canApprove = user.role === 'relief_officer' || user.role === 'line_manager'
  if (page === 'request') return <main className="portal"><Sidebar user={user} admin={admin} page={page} onNavigate={setPage} onLogout={logout} unreadCount={notifications.filter((item) => !item.read_at).length} /><section className="content"><EmployeePortal leaveTypes={leaveTypes} requests={admin ? requests.filter((request) => Number(request.employee_id) === Number(user.id)) : requests} onRefresh={refresh} user={user} balances={balances} /></section></main>
  if (page === 'attendance') return <main className="portal"><Sidebar user={user} admin={admin} page={page} onNavigate={setPage} onLogout={logout} unreadCount={notifications.filter((item) => !item.read_at).length} /><section className="content"><AttendanceView admin={admin} /></section></main>
  if (page === 'availability' && (admin || canApprove)) return <main className="portal"><Sidebar user={user} admin={admin} page={page} onNavigate={setPage} onLogout={logout} unreadCount={notifications.filter((item) => !item.read_at).length} /><section className="content"><TeamAvailabilityView /></section></main>
  if (page === 'delegations' && canApprove) return <main className="portal"><Sidebar user={user} admin={admin} page={page} onNavigate={setPage} onLogout={logout} unreadCount={notifications.filter((item) => !item.read_at).length} /><section className="content"><DelegationsView /></section></main>
  return <main className="portal"><Sidebar user={user} admin={admin} page={page} onNavigate={setPage} onLogout={logout} unreadCount={notifications.filter((item) => !item.read_at).length} /><section className="content">{page === 'profile' ? <ProfileView /> : page === 'employees' && admin ? <EmployeeManagement /> : page === 'payroll-setup' && admin ? <OrganizationPayrollSettings /> : page === 'lifecycle' && admin ? <EmployeeLifecycleView /> : page === 'reports' && admin ? <ReportsView requests={requests} /> : page === 'audit-log' && admin ? <AuditLogView /> : page === 'inbox' && canApprove ? <ApprovalInbox user={user} onRefresh={refresh} /> : page === 'balances' ? <BalanceView balances={balances} /> : page === 'calendar' ? <CalendarView canManageHolidays={admin} /> : page === 'notifications' ? <NotificationsView items={notifications} onRefresh={refresh} /> : page === 'dashboard' && canApprove ? <ApproverDashboard user={user} requests={requests} onOpenInbox={() => setPage('inbox')} /> : admin ? <AdminPortal requests={requests} onEmployees={() => setPage('employees')} /> : <EmployeePortal leaveTypes={leaveTypes} requests={requests} onRefresh={refresh} user={user} balances={balances} />}</section></main>
}
