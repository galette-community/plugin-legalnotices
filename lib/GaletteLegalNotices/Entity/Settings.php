<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace GaletteLegalNotices\Entity;

use Throwable;
use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\I18n;

/**
 * Settings for Legal Notices
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 *
 * @property boolean $enable_legal_information Enable Legal Information page
 * @property boolean $enable_terms_of_service Enable Terms & Conditions page
 * @property boolean $enable_privacy_policy Enable Privacy Policy page
 * @property boolean $publicpage_links Link pages in the public pages menu
 * @property boolean $fallback_language Language used for all untranslated pages
 * @property boolean $enable_cmp Enable the consent management plateform
 * @property boolean $hide_accept_all Hide the "Accept all" button
 * @property boolean $hide_decline_all Hide the "I decline" button
 * @property integer $cookie_expiration Cookie lifetime
 * @property string $cookie_domain Change the cookie domain
 * @property string $enable_localstorage Store consent information in the browser with localStorage
 */

class Settings
{
    /** @var array<string, bool|int|string> */
    private array $settings;
    /** @var array<string> */
    private array $enabled_pages;
    /** @var array<string> */
    private array $errors = [];

    public const TABLE = 'settings';
    public const PK = 'name';

    /** @var array<string> */
    private static array $fields = [
        'name',
        'value'
    ];

    /** @var array<string, int|string|bool> */
    private static array $defaults = [
        'enable_legal_information' => false,
        'enable_terms_of_service' => false,
        'enable_privacy_policy' => false,
        'publicpage_links' => false,
        'fallback_language' => I18n::DEFAULT_LANG,
        'enable_cmp' => false,
        'hide_accept_all' => false,
        'hide_decline_all' => false,
        'cookie_expiration' => 90,
        'cookie_domain' => '',
        'enable_localstorage' => false
    ];

    /** @var array<string> */
    private static array $pages_params = [
        'enable_legal_information',
        'enable_terms_of_service',
        'enable_privacy_policy'
    ];

    private Db $zdb;

    /**
     * Main constructor
     *
     * @param Db      $zdb  Database instance
     * @param boolean $load Automatically load preferences on load
     *
     * @return void
     */
    public function __construct(Db $zdb, bool $load = true)
    {
        $this->zdb = $zdb;
        if ($load) {
            $this->load();
            $this->checkUpdate();
        }
    }

    /**
     * Check if all fields referenced in the default array does exists,
     * create them if not
     *
     * @return boolean
     */
    private function checkUpdate(): bool
    {
        $proceed = false;
        $params = [];
        foreach (self::$defaults as $k => $v) {
            if (!isset($this->settings[$k])) {
                $this->settings[$k] = $v;
                Analog::log(
                    'The field `' . $k . '` does not exists, Legal Notices will attempt to create it.',
                    Analog::INFO
                );
                $proceed = true;
                $params[] = [
                    'name'  => $k,
                    'value'  => $v
                ];
            }
        }
        if ($proceed !== false) {
            try {
                $insert = $this->zdb->insert(LEGALNOTICES_PREFIX . self::TABLE);
                $insert->values(
                    [
                        'name'  => ':name',
                        'value'  => ':value'
                    ]
                );
                $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

                foreach ($params as $p) {
                    $stmt->execute(
                        [
                            'name' => $p['name'],
                            'value' => $p['value']
                        ]
                    );
                }
            } catch (Throwable $e) {
                Analog::log(
                    'Unable to add missing Legal Notices settings.' . $e->getMessage(),
                    Analog::WARNING
                );
                return false;
            }

            Analog::log(
                'Missing Legal Notices settings were successfully stored into database.',
                Analog::INFO
            );
        }

        return true;
    }

    /**
     * Load settings from the database
     *
     * @return boolean
     */
    public function load(): bool
    {
        $this->settings = [];
        $this->enabled_pages = [];

        try {
            $result = $this->zdb->selectAll(LEGALNOTICES_PREFIX . self::TABLE);
            foreach ($result as $setting) {
                $this->settings[$setting['name']] = $setting['value'];
                // Set enabled pages
                if (in_array($setting['name'], self::$pages_params) && $setting['value'] == '1') {
                    $this->enabled_pages[] = str_replace('_', '-', substr($setting['name'], 7));
                }
            }
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Legal Notices settings cannot be loaded.',
                Analog::URGENT
            );
            return false;
        }
    }

    /**
     * Initialize settings at install time
     *
     * @return boolean
     * @throws Throwable
     */
    public function installInit(): bool
    {
        try {
            //first, we drop all values
            $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . self::TABLE);
            $this->zdb->execute($delete);

            //we then insert default values
            $values = self::$defaults;
            $insert = $this->zdb->insert(LEGALNOTICES_PREFIX . self::TABLE);
            $insert->values(
                [
                    'name'  => ':name',
                    'value'  => ':value'
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

            foreach ($values as $k => $v) {
                $stmt->execute(
                    [
                        'name' => $k,
                        'value' => $v
                    ]
                );
            }

            Analog::log(
                'Default Legal Notices settings were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default Legal Notices settings.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Returns all preferences keys
     *
     * @return array<string>
     */
    public function getFieldsNames(): array
    {
        return array_keys($this->settings);
    }

    /**
     * Check values
     *
     * @param array<string, mixed> $values Values
     *
     * @return void
     */
    public function check(array $values): void
    {
        $insert_values = [];

        // obtain fields
        foreach ($this->getFieldsNames() as $fieldname) {
            if (isset($values[$fieldname])) {
                if (is_string($values[$fieldname])) {
                    $value = trim($values[$fieldname]);
                } else {
                    $value = $values[$fieldname];
                }
            } else {
                $value = "";
            }

            $insert_values[$fieldname] = $value;
        }

        // update settings
        foreach ($insert_values as $field => $value) {
            $this->$field = $value;
        }

        return;
    }

    /**
     * Store values in the database
     *
     * @return boolean
     */
    public function store(): bool
    {
        try {
            $this->zdb->connection->beginTransaction();
            $update = $this->zdb->update(LEGALNOTICES_PREFIX . self::TABLE);
            $update->set(
                [
                    'value'  => ':value'
                ]
            )->where->equalTo('name', ':name');

            $stmt = $this->zdb->sql->prepareStatementForSqlObject($update);

            foreach (self::$defaults as $k => $v) {
                Analog::log('Storing Legal Notices ' . $k, Analog::DEBUG);

                $value = $this->settings[$k];

                $stmt->execute(
                    [
                        'value'  => $value,
                        'name'  => $k
                    ]
                );
            }
            $this->zdb->connection->commit();
            Analog::log(
                'Legal Notices settings were successfully stored into database.',
                Analog::INFO
            );

            return true;
        } catch (\Exception $e) {
            if ($this->zdb->connection->inTransaction()) {
                $this->zdb->connection->rollBack();
            }

            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            Analog::log(
                'Unable to store Legal Notices settings | ' . print_r($messages, true),
                Analog::WARNING
            );
            return false;
        }
    }

    /**
     * Global getter method
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return mixed the called property
     */
    public function __get(string $name): mixed
    {
        $types = [
            'int' => [
                'cookie_expiration'
            ],
            'bool' => [
                'enable_legal_information',
                'enable_terms_of_service',
                'enable_privacy_policy',
                'publicpage_links',
                'enable_cmp',
                'hide_accept_all',
                'hide_decline_all',
                'enable_localstorage'
            ]
        ];

        if (isset($this->settings[$name])) {
            $value = $this->settings[$name];
            if (in_array($name, $types['int']) && $value !== '') {
                $value = (int)$value;
            }

            if (in_array($name, $types['bool']) && $value !== '') {
                $value = (bool)$value;
            }

            return $value;
        } else {
            Analog::log(
                'Legal Notices setting `' . $name . '` is not set',
                Analog::INFO
            );
            return false;
        }
    }

    /**
     * Global isset method
     * Required for twig to access properties via __get
     *
     * @param string $name name of the property we want to retrieve
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (isset($this->settings[$name])) {
            return true;
        } else {
            Analog::log(
                'Legal Notices setting `' . $name . '` is not set',
                Analog::INFO
            );
            return false;
        }
    }

    /**
     * Get default settings
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return self::$defaults;
    }

    /**
     * Global setter method
     *
     * @param string $name  name of the property we want to assign a value to
     * @param mixed  $value a relevant value for the property
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        //does this setting exists ?
        if (!array_key_exists($name, self::$defaults)) {
            Analog::log(
                'Trying to set a Legal Notices setting value which does not seem to exist ('
                . $name . ')',
                Analog::WARNING
            );
            return;
        }

        //okay, let's update value
        $this->settings[$name] = $value;
    }

    /**
     * Returns all settings
     *
     * @return array<string>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get fallback language
     *
     * @return string
     */
    public function getFallbackLanguage(): string
    {
        return $this->settings['fallback_language'];
    }

    /**
     * Check if the specified page is disabled
     *
     * @param string $name page name
     *
     * @return boolean
     */
    public function isPageEnabled(string $name): bool
    {
        return in_array($name, $this->enabled_pages);
    }

    /**
     * Get errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
