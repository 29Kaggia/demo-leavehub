<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Utility\DocumentStorage;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\UploadedFileInterface;

class ProfilesController extends AppController
{
    public function view(): \Cake\Http\Response
    {
        $employee = TableRegistry::getTableLocator()->get('Employees')->find()
            ->contain(['Departments', 'LineManagers', 'ReliefOfficers', 'EmployeeDocuments'])
            ->where(['Employees.id' => $this->currentEmployee()->id])->firstOrFail();
        return $this->json(['data' => $employee]);
    }

    public function update(): \Cake\Http\Response
    {
        $this->request->allowMethod(['patch']);
        $employees = TableRegistry::getTableLocator()->get('Employees');
        $employee = $employees->get($this->currentEmployee()->id);
        $dateOfBirth = trim((string)$this->request->getData('date_of_birth'));
        if ($dateOfBirth !== '') {
            try { new \DateTimeImmutable($dateOfBirth); } catch (\Throwable) { throw new BadRequestException('Provide a valid date of birth.'); }
        }
        $employee->date_of_birth = $dateOfBirth ?: null;
        if (!$employees->save($employee)) throw new BadRequestException('Profile could not be updated.');
        return $this->view();
    }

    public function upload(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $file = $this->request->getUploadedFile('document') ?? $this->request->getData('document');
        $type = trim((string)$this->request->getData('document_type'));
        if (!$type) throw new BadRequestException('Choose a document type.');
        if (!$file instanceof UploadedFileInterface) throw new BadRequestException('Choose a document file no larger than 30 MB.');
        if ($file->getError() === UPLOAD_ERR_INI_SIZE) throw new BadRequestException('This document exceeds the server upload limit of 30 MB.');
        if ($file->getError() !== UPLOAD_ERR_OK) throw new BadRequestException('The document upload did not complete. Please choose the file again and retry.');
        if (($file->getSize() ?? 0) > 30 * 1024 * 1024) throw new BadRequestException('Documents must be 30 MB or smaller.');
        $extension = pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $directory = DocumentStorage::directory('employee-documents');
        $file->moveTo($directory . DS . $filename);
        $documents = TableRegistry::getTableLocator()->get('EmployeeDocuments');
        $document = $documents->newEntity(['employee_id' => $this->currentEmployee()->id, 'document_type' => $type, 'original_filename' => $file->getClientFilename() ?? 'document', 'storage_path' => 'uploads/employee-documents/' . $filename, 'mime_type' => $file->getClientMediaType() ?? 'application/octet-stream', 'size_bytes' => $file->getSize() ?? 0, 'review_status' => 'pending']);
        if (!$documents->save($document)) throw new BadRequestException('Document could not be saved.');
        return $this->json(['data' => $document], 201);
    }

    public function download(int $id): \Cake\Http\Response
    {
        $viewer = $this->currentEmployee();
        $documents = TableRegistry::getTableLocator()->get('EmployeeDocuments');
        $document = $documents->find()->contain(['Employees'])->where(['EmployeeDocuments.id' => $id])->firstOrFail();
        if ((int)$document->employee->organization_id !== (int)$viewer->organization_id || ($viewer->role !== 'hr_admin' && (int)$viewer->id !== (int)$document->employee_id)) throw new ForbiddenException('You cannot access this document.');
        $path = DocumentStorage::path($document->storage_path);
        if (!is_file($path)) throw new NotFoundException('Document file not found.');
        return $this->response->withFile($path, ['download' => true, 'name' => $document->original_filename]);
    }
}
