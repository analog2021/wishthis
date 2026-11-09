<?php

/**
 * options.php
 *
 * Store and retrieve application options.
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

class Options
{
    public string $version;

    public function __construct(private Database $database)
    {
        $this->version = $this->getOption('version')
                       ? $this->getOption('version')
                       : '0.1.0';
    }

    /**
     * Returns the database changes (`src/update/<major>-<minor>-<patch>.sql`)
     * which haven't been made yet, oldest first.
     *
     * A file of the same name in a directory named after the database engine
     * (e. g. `sqlite/1-4-0.sql`) replaces the shared one, for changes which
     * can't be written for both engines.
     *
     * @return array<int, array{version: string, filepath: string}>
     */
    public function getPendingUpdates(): array
    {
        $directory = ROOT . '/src/update';
        $updates   = [];

        foreach (\scandir($directory) ?: [] as $filename) {
            $filepath = $directory . '/' . $filename;
            $pathinfo = \pathinfo($filepath);

            if (!\is_file($filepath) || 'sql' !== ($pathinfo['extension'] ?? '')) {
                continue;
            }

            $version = \str_replace('-', '.', $pathinfo['filename']);

            if (-1 !== \version_compare($this->version, $version)) {
                continue;
            }

            $filepathEngine = $directory . '/' . $this->database->engine . '/' . $filename;

            $updates[] = [
                'version'  => $version,
                'filepath' => \is_file($filepathEngine) ? $filepathEngine : $filepath,
            ];
        }

        \usort(
            $updates,
            function (array $a, array $b): int {
                return \version_compare($a['version'], $b['version']);
            }
        );

        return $updates;
    }

    /**
     * Records that the database is up to date with this version of wishthis.
     */
    public function completeUpdate(): void
    {
        $this->setOption('version', VERSION);
        $this->setOption('updateAvailable', false);
        $this->version = VERSION;

        /** The list of files to cache offline */
        require ROOT . '/src/assets/js/service-worker.js.php';
    }

    public function getOption(string $key): string
    {
        $value = '';

        try {
            $option = $this->database->query(
                'SELECT *
                   FROM `options`
                  WHERE `key` = :option_key',
                [
                    'option_key' => Sanitiser::getOption($key),
                ]
            )
            ->fetch();

            $value = $option['value'] ?? '';
        } catch (\Throwable $th) {
            /** Option does not exist */
        }

        return $value;
    }

    public function setOption(string $key, string $value): void
    {
        $optionExists = 0 !== $this->database
        ->query(
            'SELECT *
               FROM `options`
              WHERE `key` = :option_key;',
            [
                'option_key' => $key,
            ]
        )
        ->rowCount();

        if ($optionExists) {
            $this->database->query(
                'UPDATE `options`
                    SET `value` = :option_value
                  WHERE `key`   = :option_key;',
                [
                    'option_value' => $value,
                    'option_key'   => $key,
                ]
            );
        } else {
            $this->database->query(
                'INSERT INTO `options`
                    (`key`, `value`)
                VALUES
                    (:option_key, :option_value);',
                [
                    'option_key'   => $key,
                    'option_value' => $value,
                ]
            );
        }
    }
}
