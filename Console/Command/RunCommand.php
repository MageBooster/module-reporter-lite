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

namespace MageBooster\ReporterLite\Console\Command;

use MageBooster\ReporterLite\Cron\Cleanup;
use MageBooster\ReporterLite\Model\Delivery;
use MageBooster\ReporterLite\Model\Licence;
use MageBooster\ReporterLite\Model\Repository;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento mbreporterlite:run <id> [--test-to=me@example.com] [--eml=/tmp/out]
 * Sends a saved report now (as the schedule would), or a test to one address. --cleanup runs the cleanup.
 */
class RunCommand extends Command
{
    /**
     * @param Delivery $delivery
     * @param Repository $repository
     * @param Licence $licence
     * @param Cleanup $cleanup
     * @param State $state
     */
    public function __construct(
        private Delivery $delivery,
        private Repository $repository,
        private Licence $licence,
        private Cleanup $cleanup,
        private State $state
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('mbreporterlite:run')
            ->setDescription('Send a Reporter Lite report now, or a test to one address')
            ->addArgument('id', InputArgument::OPTIONAL, 'Report ID (leave out to list reports)')
            ->addOption('test-to', null, InputOption::VALUE_REQUIRED, 'Send a test to this address only')
            ->addOption('eml', null, InputOption::VALUE_REQUIRED, 'Write the e-mail as a .eml file into this folder')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Delete expired files and old history');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Throwable $e) {
            // area already set
            $e = null;
        }
        if (!$this->licence->isActive()) {
            $output->writeln('<error>' . $this->licence->message() . '</error>');
            return 1;
        }
        if ($input->getOption('cleanup')) {
            $r = $this->cleanup->execute();
            $output->writeln(sprintf('Deleted %d files and %d history rows.', $r['files'], $r['history']));
            return 0;
        }
        $id = (int)$input->getArgument('id');
        if ($id <= 0) {
            foreach ($this->repository->all() as $r) {
                $output->writeln(sprintf('%5d  %s%s', $r['report_id'], $r['name'], $r['cron_status'] ? '  [scheduled]' : ''));
            }
            return 0;
        }
        $testTo = $input->getOption('test-to');
        $eml = $input->getOption('eml');
        if ($eml !== null && !is_dir((string)$eml)) {
            $output->writeln('<error>The --eml folder does not exist.</error>');
            return 1;
        }
        $r = $this->delivery->run($id, $testTo ? 'test' : 'schedule', $testTo ? (string)$testTo : null, $eml);
        $output->writeln(sprintf('%s: %s (%d rows, run %d)', $r['result'], $r['message'], $r['rows'], $r['run_id']));
        return $r['result'] === 'failed' ? 1 : 0;
    }
}
