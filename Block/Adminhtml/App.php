<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\ReporterLite\Block\Adminhtml;

use MageBooster\ReporterLite\Model\Examples;
use MageBooster\ReporterLite\Model\Export;
use MageBooster\ReporterLite\Model\Config;
use MageBooster\ReporterLite\Model\Params;
use MageBooster\ReporterLite\Model\Presenter;
use MageBooster\ReporterLite\Model\Sql\Connection;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Data for the Reporter Lite page script.
 */
class App extends Template
{
    /**
     * @param Context $context
     * @param Presenter $presenter
     * @param Examples $examples
     * @param Export $export
     * @param Params $params
     * @param Config $config
     * @param Connection $connection
     * @param Session $session
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        private Presenter $presenter,
        private Examples $examples,
        private Export $export,
        private Params $params,
        private Config $config,
        private Connection $connection,
        private Session $session,
        private Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Header text and dot colour for the database connection
     *
     * @return array{state:string,text:string}
     */
    public function connectionState(): array
    {
        $s = $this->connection->status();
        $texts = [
            'ok' => __('Read-only database user · connected'),
            'writable' => __('The report user can change data. Give it SELECT only.'),
            'error' => __('The read-only database user cannot connect'),
            'missing' => __('No read-only database user set up yet'),
        ];
        return ['state' => $s['state'], 'text' => (string)$texts[$s['state']]];
    }

    /**
     * JSON for the page script
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        $auth = $this->_authorization;
        $user = $this->session->getUser();
        $formats = [];
        $labels = ['csv' => 'CSV', 'xlsx' => 'Excel', 'json' => 'JSON', 'html' => 'HTML', 'pdf' => 'PDF'];
        foreach ($this->export->available() as $key => $on) {
            if ($on) {
                $formats[] = ['key' => $key, 'label' => $labels[$key]];
            }
        }
        $presets = [];
        foreach (Params::PRESETS as $k => $v) {
            $presets[] = ['value' => $k, 'label' => (string)__($v)];
        }
        $ranges = [];
        foreach (Params::RANGES as $k => $v) {
            $ranges[] = ['value' => $k, 'label' => (string)__($v)];
        }
        return $this->json->serialize([
            'urls' => [
                'load' => $this->getUrl('mbreporterlite/report/load'),
                'save' => $this->getUrl('mbreporterlite/report/save'),
                'delete' => $this->getUrl('mbreporterlite/report/delete'),
                'run' => $this->getUrl('mbreporterlite/report/run'),
                'export' => $this->getUrl('mbreporterlite/report/export'),
                'test' => $this->getUrl('mbreporterlite/report/test'),
                'next' => $this->getUrl('mbreporterlite/report/next'),
                'settings' => $this->getUrl('adminhtml/system_config/edit', ['section' => 'magebooster_reporterlite']),
            ],
            'formKey' => $this->getFormKey(),
            'reports' => $this->presenter->items(),
            'stores' => $this->params->storeOptions(),
            'defaultStore' => $this->params->defaultValue('store'),
            'formats' => $formats,
            'presets' => $presets,
            'ranges' => $ranges,
            'examples' => $this->examples->all(),
            'steps' => $this->examples->steps(),
            'tables' => $this->examples->tables(),
            'traps' => $this->examples->traps(),
            'acl' => [
                'edit' => $auth->isAllowed('MageBooster_ReporterLite::edit'),
                'schedule' => $auth->isAllowed('MageBooster_ReporterLite::schedule'),
            ],
            'me' => $user ? (string)$user->getEmail() : '',
            'timezone' => $this->config->timezone(),
            'linkDays' => $this->config->linkDays(),
            'i18n' => $this->dictionary(),
        ]);
    }

    /**
     * Texts used by the page script
     *
     * @return array<string,string>
     */
    private function dictionary(): array
    {
        $t = [
            'Last run', 'Next run', 'Recipients', 'Last result', 'Never', 'Not scheduled', 'Nobody yet',
            '%1 rows', '1 row', '1 person', '%1 people', 'Query', 'Schedule', 'History', 'Save', 'Saving…',
            'Saved', 'Duplicate', 'Delete', 'Run', 'Running…', 'Format', 'Parameters', 'No parameters. Write :from, :to or :name in the query to add one.',
            'Result', '%1 rows · %2 s', 'Showing the first %1 of %2 rows', 'more than %1', 'Run the query to see the result.',
            'The query returned no rows.', 'Show chart', 'Hide chart', 'Not saved yet', 'Unsaved changes',
            'You have unsaved changes. Leave them?', 'Delete this report, its history and its files?',
            'New report', 'Report name', 'Untitled report', 'Copy of %1', 'Send automatically', 'On', 'How often',
            'Daily', 'Weekly', 'Monthly', 'Custom', 'every day', 'one day a week', 'one date a month', 'cron expression',
            'Day', 'Day of the month', 'Time', 'Data covers', 'Next send:', 'covering %1', 'Use a cron expression',
            'Use the simple schedule', 'Cron expression', 'Add an e-mail…', 'Remove %1', 'File', 'Delivery',
            'Attach to the e-mail', 'Download link (%1 days)', "Don't send when there are no rows", 'Send a test to me',
            'Sending…', 'Save the report first.', 'Save your changes first.', 'Run history', 'No runs yet.',
            'schedule', 'test', 'Download', 'not kept', 'Downloading…', 'Monday', 'Tuesday', 'Wednesday', 'Thursday',
            'Friday', 'Saturday', 'Sunday', 'Pick a date', 'Store view', 'Search reports', 'My reports',
            'No reports yet. Start with New report or pick one from the library.', 'No report matches.',
            'Start from the library', 'Scheduled', 'Paused', 'Manual',
            'Write your own query in the editor below, or start from an example and change it',
            'About this example', 'Columns you get', 'Change it like this', 'How to write a report query',
            'Where the data is', 'Common mistakes', 'SELECT only', 'SQL query', 'Something went wrong. Try again.',
            'Your admin role can run saved reports but not change them.', 'Store time (%1)', 'Times are in store time (%1).',
            'Add at least one recipient to send automatically.', '"%1" is not a valid e-mail address.',
            'Test sent to %1', 'Close', 'The schedule is off. Turn it on to send automatically.',
            'Only the scheduled run uses this. Run and downloads use the parameter values above.',
            'Bar chart of %1 by %2', 'Value',
        ];
        $out = [];
        foreach ($t as $s) {
            $out[$s] = (string)__($s);
        }
        return $out;
    }
}
