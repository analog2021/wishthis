<?php

namespace wishthis;

class Config
{
    public function __construct(private string $filePath)
    {
    }

    public function exists(): bool
    {
        return \file_exists($this->filePath);
    }

    public function load(): bool
    {
        $exists = $this->exists();

        if ($exists) {
            require $this->filePath;

            $this->applyLegacyDefaults();
        }

        return $exists;
    }

    /**
     * Fills in settings which configurations written by older versions of
     * wishthis don't contain, so existing installations keep working after
     * an update.
     *
     * @return void
     */
    private function applyLegacyDefaults(): void
    {
        /**
         * Configurations from v1.2.3 and older predate SQLite support, so their
         * database is always MySQL.
         */
        if (\defined('DATABASE_HOST') && !\defined('DATABASE_ENGINE')) {
            \define('DATABASE_ENGINE', 'mysql');
        }
    }
}
