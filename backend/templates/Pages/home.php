<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.10.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Error\Debugger;
use Cake\Http\Exception\NotFoundException;

$this->disableAutoLayout();

$checkConnection = function (string $name) {
    $error = null;
    $connected = false;
    try {
        ConnectionManager::get($name)->getDriver()->connect();
        // No exception means success
        $connected = true;
    } catch (Exception $connectionError) {
        $error = $connectionError->getMessage();
        if (method_exists($connectionError, 'getAttributes')) {
            $attributes = $connectionError->getAttributes();
            if (isset($attributes['message'])) {
                $error .= '<br />' . $attributes['message'];
            }
        }
        if ($name === 'debug_kit') {
            $error = 'Try adding your current <b>top level domain</b> to the
                <a href="https://book.cakephp.org/debugkit/5/en/index.html#configuration" target="_blank">DebugKit.safeTld</a>
            config and reload.';
            if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
                $error .= '<br />You need to install the PHP extension <code>pdo_sqlite</code> so DebugKit can work properly.';
            }
        }
    }

    return compact('connected', 'error');
};

if (!Configure::read('debug')) :
    throw new NotFoundException(
        'Please replace templates/Pages/home.php with your own version or re-enable debug mode.'
    );
endif;

?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        CakePHP: the rapid development PHP framework:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>

    <style>
        :root {
            --bg: #0c0e12;
            --surface: #15181e;
            --surface-raised: #191d24;
            --border: #262b34;
            --text: #e7e9ee;
            --muted: #8b93a1;
            --cake: #e2434b;
            --cake-dim: #e2434b1a;
            --ok: #34d399;
            --ok-dim: #34d3991a;
            --warn: #f5a623;
            --warn-dim: #f5a6231a;
            --radius: 3px;

            --font-display: 'Avenir Next', 'Segoe UI Semibold', 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif;
            --font-body: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            --font-mono: ui-monospace, 'SF Mono', 'Cascadia Code', 'Segoe UI Mono', 'Roboto Mono', Menlo, Consolas, 'Liberation Mono', monospace;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.6;
        }

        .wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 4rem 1.5rem 5rem;
        }

        header.hero {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 3rem;
        }

        header.hero img {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
        }

        h1 {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.55rem;
            line-height: 1.3;
            margin: 0;
            color: var(--text);
        }

        h1 .version {
            color: var(--cake);
        }

        .notice {
            font-size: 0.85rem;
            color: var(--muted);
            border-left: 2px solid var(--border);
            padding-left: 0.9rem;
            margin-bottom: 2.5rem;
        }

        .rewrite-warning {
            background: var(--warn-dim);
            border: 1px solid #f5a62333;
            border-radius: var(--radius);
            padding: 1.1rem 1.3rem;
            margin-bottom: 2.5rem;
        }

        .rewrite-warning p {
            margin: 0 0 0.6rem;
            font-weight: 500;
            color: var(--text);
        }

        .rewrite-warning ol {
            margin: 0;
            padding-left: 1.2rem;
            color: var(--muted);
        }

        .rewrite-warning a { color: var(--warn); }

        section {
            margin-bottom: 2.75rem;
        }

        h4.section-title {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
            color: var(--text);
            margin: 0 0 0.85rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.75rem 2rem;
        }

        @media (max-width: 620px) {
            .status-grid { grid-template-columns: 1fr; }
            header.hero { gap: 1rem; }
            header.hero img { width: 44px; height: 44px; }
            h1 { font-size: 1.25rem; }
        }

        .status-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .status-item {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            padding: 0.55rem 0 0.55rem 0.85rem;
            border-left: 2px solid var(--border);
            font-family: var(--font-mono);
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.55;
        }

        .status-item.ok { border-left-color: var(--ok); }
        .status-item.bad { border-left-color: var(--cake); }

        .status-item .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-top: 0.5em;
            flex-shrink: 0;
        }

        .status-item.ok .dot { background: var(--ok); }
        .status-item.bad .dot { background: var(--cake); }

        .status-item .msg { color: var(--text); }
        .status-item.bad .msg { color: #f2a2a6; }
        .status-item .detail {
            display: block;
            color: var(--muted);
            font-size: 0.78rem;
            margin-top: 0.2rem;
        }

        .status-item code {
            background: var(--surface-raised);
            border: 1px solid var(--border);
            border-radius: 2px;
            padding: 0.05rem 0.3rem;
            font-family: var(--font-mono);
            font-size: 0.78rem;
        }

        hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 2.5rem 0;
        }

        .links-block h3 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 0.75rem;
            color: var(--text);
        }

        .links-block {
            margin-bottom: 1.75rem;
        }

        .links-block .link-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem 1.5rem;
        }

        .links-block a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.88rem;
            border-bottom: 1px solid transparent;
        }

        .links-block a:hover {
            color: var(--text);
            border-bottom-color: var(--cake);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <?= $this->Html->image('https://cakephp.org/v2/img/logos/CakePHP_Icon.svg', ['alt' => 'CakePHP', 'onerror' => "this.style.display='none'"]) ?>
            <h1>Welcome to <span class="version">CakePHP <?= h(Configure::version()) ?></span> — Chiffon 🍰</h1>
        </header>

        <p class="notice">This page won't show once debug mode is off, unless you replace <code>templates/Pages/home.php</code> with your own version.</p>

        <div class="rewrite-warning">
            <p>URL rewriting is not properly configured on your server.</p>
            <ol>
                <li><a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/installation.html#url-rewriting">Help me configure it</a></li>
                <li><a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/development/configuration.html#general-configuration">I don't / can't use URL rewriting</a></li>
            </ol>
        </div>
        <?php Debugger::checkSecurityKeys(); ?>

        <div class="status-grid">
            <section>
                <h4 class="section-title">Environment</h4>
                <ul class="status-list">
                <?php if (version_compare(PHP_VERSION, '8.1.0', '>=')) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">PHP <?= PHP_VERSION ?> — meets the 8.1.0 minimum</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">PHP <?= PHP_VERSION ?> — needs 8.1.0 or higher</span></li>
                <?php endif; ?>

                <?php if (extension_loaded('mbstring')) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">mbstring extension loaded</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">mbstring extension missing</span></li>
                <?php endif; ?>

                <?php if (extension_loaded('openssl')) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">openssl extension loaded</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">openssl extension missing</span></li>
                <?php endif; ?>

                <?php if (extension_loaded('intl')) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">intl extension loaded</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">intl extension missing</span></li>
                <?php endif; ?>

                <?php if (ini_get('zend.assertions') !== '1') : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">Set <code>zend.assertions</code> to <code>1</code> in php.ini for development</span></li>
                <?php endif; ?>
                </ul>
            </section>

            <section>
                <h4 class="section-title">Filesystem</h4>
                <ul class="status-list">
                <?php if (is_writable(TMP)) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">tmp/ is writable</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">tmp/ is NOT writable</span></li>
                <?php endif; ?>

                <?php if (is_writable(LOGS)) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">logs/ is writable</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">logs/ is NOT writable</span></li>
                <?php endif; ?>

                <?php $settings = Cache::getConfig('_cake_translations_'); ?>
                <?php if (!empty($settings)) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">Core caching via <?= h($settings['className']) ?><span class="detail">Configured in config/app.php</span></span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">Cache is NOT working<span class="detail">Check config/app.php</span></span></li>
                <?php endif; ?>
                </ul>
            </section>

            <section>
                <h4 class="section-title">Database</h4>
                <?php $result = $checkConnection('default'); ?>
                <ul class="status-list">
                <?php if ($result['connected']) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">Connected to the database</span></li>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">Cannot connect to the database<span class="detail"><?= h($result['error']) ?></span></span></li>
                <?php endif; ?>
                </ul>
            </section>

            <section>
                <h4 class="section-title">DebugKit</h4>
                <ul class="status-list">
                <?php if (Plugin::isLoaded('DebugKit')) : ?>
                    <li class="status-item ok"><span class="dot"></span><span class="msg">DebugKit is loaded</span></li>
                    <?php $result = $checkConnection('debug_kit'); ?>
                    <?php if ($result['connected']) : ?>
                        <li class="status-item ok"><span class="dot"></span><span class="msg">DebugKit can connect to the database</span></li>
                    <?php else : ?>
                        <li class="status-item bad"><span class="dot"></span><span class="msg">Configuration problem<span class="detail"><?= $result['error'] ?></span></span></li>
                    <?php endif; ?>
                <?php else : ?>
                    <li class="status-item bad"><span class="dot"></span><span class="msg">DebugKit is not loaded</span></li>
                <?php endif; ?>
                </ul>
            </section>
        </div>

        <hr>

        <div class="links-block">
            <h3>Getting started</h3>
            <div class="link-row">
                <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/">CakePHP Documentation</a>
                <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/tutorials-and-examples/cms/installation.html">The 20 min CMS Tutorial</a>
            </div>
        </div>

        <div class="links-block">
            <h3>Help and bug reports</h3>
            <div class="link-row">
                <a target="_blank" rel="noopener" href="https://slack-invite.cakephp.org/">Slack</a>
                <a target="_blank" rel="noopener" href="https://github.com/cakephp/cakephp/issues">CakePHP Issues</a>
                <a target="_blank" rel="noopener" href="https://discourse.cakephp.org/">CakePHP Forum</a>
            </div>
        </div>

        <div class="links-block">
            <h3>Docs and downloads</h3>
            <div class="link-row">
                <a target="_blank" rel="noopener" href="https://api.cakephp.org/">CakePHP API</a>
                <a target="_blank" rel="noopener" href="https://bakery.cakephp.org">The Bakery</a>
                <a target="_blank" rel="noopener" href="https://book.cakephp.org/5/en/">CakePHP Documentation</a>
                <a target="_blank" rel="noopener" href="https://plugins.cakephp.org">CakePHP plugins repo</a>
                <a target="_blank" rel="noopener" href="https://github.com/cakephp/">CakePHP Code</a>
                <a target="_blank" rel="noopener" href="https://github.com/FriendsOfCake/awesome-cakephp">CakePHP Awesome List</a>
                <a target="_blank" rel="noopener" href="https://www.cakephp.org">CakePHP</a>
            </div>
        </div>

        <div class="links-block">
            <h3>Training and certification</h3>
            <div class="link-row">
                <a target="_blank" rel="noopener" href="https://cakefoundation.org/">Cake Software Foundation</a>
                <a target="_blank" rel="noopener" href="https://training.cakephp.org/">CakePHP Training</a>
            </div>
        </div>
    </div>
</body>
</html>