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

namespace MageBooster\ReporterLite\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

/**
 * Report files in var/mbreporterlite (outside the web root). Names are random; a download link carries a
 * random token of which only the SHA-256 is stored.
 */
class Files
{
    public const DIR = 'mbreporterlite';

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * Stores a file and returns its stored name
     *
     * @param string $body
     * @param string $extension
     * @return string
     */
    public function save(string $body, string $extension): string
    {
        $dir = $this->dir();
        $dir->create(self::DIR);
        $name = bin2hex(random_bytes(16)) . '.' . preg_replace('/[^a-z0-9]/', '', strtolower($extension));
        $dir->writeFile(self::DIR . '/' . $name, $body);
        return $name;
    }

    /**
     * Contents of a stored file, or null if it is gone
     *
     * @param string $name
     * @return string|null
     */
    public function read(string $name): ?string
    {
        if (!$this->validName($name)) {
            return null;
        }
        $dir = $this->dir();
        $path = self::DIR . '/' . $name;
        return $dir->isFile($path) ? $dir->readFile($path) : null;
    }

    /**
     * Deletes a stored file
     *
     * @param string $name
     * @return void
     */
    public function delete(string $name): void
    {
        if ($this->validName($name)) {
            $dir = $this->dir();
            if ($dir->isFile(self::DIR . '/' . $name)) {
                $dir->delete(self::DIR . '/' . $name);
            }
        }
    }

    /**
     * New download token: [token for the link, hash to store]
     *
     * @return array{0:string,1:string}
     */
    public function newToken(): array
    {
        $token = bin2hex(random_bytes(32));
        return [$token, hash('sha256', $token)];
    }

    /**
     * Hash of a token from a link (null when it does not look like a token)
     *
     * @param string $token
     * @return string|null
     */
    public function hashToken(string $token): ?string
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) ? hash('sha256', $token) : null;
    }

    /**
     * Stored names in the folder
     *
     * @return string[]
     */
    public function all(): array
    {
        $dir = $this->dir();
        if (!$dir->isDirectory(self::DIR)) {
            return [];
        }
        $out = [];
        foreach ($dir->read(self::DIR) as $path) {
            $base = basename($path);
            if ($this->validName($base)) {
                $out[] = $base;
            }
        }
        return $out;
    }

    /**
     * Only names this class creates
     *
     * @param string $name
     * @return bool
     */
    private function validName(string $name): bool
    {
        return (bool)preg_match('/^[a-f0-9]{32}\.(csv|xlsx|json|html|pdf)$/', $name);
    }

    /**
     * Writable var directory
     *
     * @return WriteInterface
     */
    private function dir(): WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }
}
