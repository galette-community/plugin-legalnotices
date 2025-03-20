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

use ArrayObject;
use Galette\Core\I18n;
use Galette\Core\Preferences;
use Galette\Features\Replacements;
use Slim\Routing\RouteParser;
use Throwable;
use Analog\Analog;
use Laminas\Db\Sql\Expression;

/**
 * Pages for Legal Notices
 *
 * @author John Perr <johnperr@abul.org>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class Pages
{
    use Replacements {
        getLegend as protected trait_getLegend;
    }

    /** @var ArrayObject<string, int|string> */
    private ArrayObject $current_page;
    public const TABLE = 'pages';
    public const PK = 'id';
    public const DEFAULT_NAME = 'legal-information';

    /** @var array<int, mixed> */
    private array $defaults;
    /** @var array<int, string> */
    private array $translated = [];

    /**
     * Main constructor
     *
     * @param Preferences      $preferences Galette's preferences
     * @param RouteParser|null $routeparser RouteParser instance
     */
    public function __construct(Preferences $preferences, ?RouteParser $routeparser = null)
    {
        global $zdb, $login, $container;
        $this->preferences = $preferences;
        if ($routeparser === null) {
            $routeparser = $container->get(RouteParser::class);
        }
        if ($login === null) {
            $login = $container->get('login');
        }
        $this->routeparser = $routeparser;
        $this
            ->setDb($zdb)
            ->setLogin($login);

        $this->setPatterns(
            $this->getMainPatterns()
            + $this->getPagesPatterns()
        );
        $this
            ->setMain()
            ->setPagesPatterns();

        $this->checkUpdate();
        $this->checkTranslated();
    }

    /**
     * Get patterns for pages
     *
     * @param boolean $legacy Whether to load legacy patterns
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getPagesPatterns(bool $legacy = true): array
    {
        $p_patterns = [
            'asso_phone_link' => [
                'title'       => _T("Your organisation phone number link", "legalnotices"),
                'pattern'     => '/{ASSO_PHONE_LINK}/',
            ],
            'asso_email_link' => [
                'title'       => _T("Your organisation email address link", "legalnotices"),
                'pattern'     => '/{ASSO_EMAIL_LINK}/',
            ]
        ];

        return $p_patterns;
    }

    /**
     * Set pages replacements
     *
     * @return self
     */
    public function setPagesPatterns(): self
    {
        $phone_number = $this->preferences->getPhoneNumber();
        $phone_link = '<a href="tel:' . preg_replace('/[^0-9+]/', '', $phone_number) . '">' . $phone_number . '</a>';

        // Obfuscate email address to prevent from being collected by spambots.
        $email_parts = explode('@', $this->preferences->pref_org_email);
        $user_part = $email_parts[0];
        $domain_part = str_replace('.', '<span class="p"> [dot] </span>', $email_parts[1]);
        $regs = [
            '/%user/',
            '/%domain/'
        ];
        $replacements = [
            $user_part,
            $domain_part
        ];
        $link = '<span class="obfuscate"><span class="u">%user</span> [at] <span class="d">%domain</span></span>';
        $email_link = preg_replace($regs, $replacements, $link);

        $this->setReplacements([
            'asso_phone_link' => $phone_link,
            'asso_email_link' => $email_link
        ]);
        return $this;
    }

    /**
     * Initialize pages at install time
     *
     * @param boolean $check_first Check first if it seems initialized
     *
     * @return boolean false if no need to initialize, true if data has been initialized, Exception if error
     * @throws Throwable
     */
    public function installInit(bool $check_first = true): bool
    {
        try {
            //first of all, let's check if data seem to have already
            //been initialized
            $this->defaults = $this->getAllDefaults(); //load defaults
            if ($check_first === true) {
                $select = $this->zdb->select(LEGALNOTICES_PREFIX . self::TABLE);
                $select->columns(
                    array(
                        'counter' => new Expression('COUNT(' . self::PK . ')')
                    )
                );

                $results = $this->zdb->execute($select);
                $result = $results->current();
                $count = $result->counter;
                if ($count < count($this->defaults)) {
                    return $this->checkUpdate();
                }
            }

            //first, we drop all values
            $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . self::TABLE);
            $this->zdb->execute($delete);

            $this->zdb->handleSequence(
                LEGALNOTICES_PREFIX . self::TABLE,
                self::PK,
                count($this->defaults)
            );

            $this->insert($this->defaults);

            Analog::log(
                'Default texts were successfully stored into database.',
                Analog::INFO
            );
            return true;
        } catch (Throwable $e) {
            Analog::log(
                'Unable to initialize default texts.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Checks for missing pages in the database
     *
     * @return boolean
     */
    private function checkUpdate(): bool
    {
        $this->defaults = $this->getAllDefaults(); //load defaults

        try {
            $select = $this->zdb->select(LEGALNOTICES_PREFIX . self::TABLE);
            $dblist = $this->zdb->execute($select);

            $list = [];
            foreach ($dblist as $dbentry) {
                $list[] = $dbentry;
            }

            $missing = [];
            foreach ($this->defaults as $default) {
                $exists = false;
                foreach ($list as $page) {
                    if (
                        $page->name == $default['name']
                        && $page->lang == $default['lang']
                    ) {
                        $exists = true;
                        continue;
                    }
                }

                if ($exists === false) {
                    //page does not exists in database, insert it.
                    $missing[] = $default;
                }
            }

            if (count($missing) > 0) {
                $this->insert($missing);

                Analog::log(
                    'Missing pages were successfully stored into database.',
                    Analog::INFO
                );
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred checking missing pages.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
        return false;
    }

    /**
     * Checks for translated pages in the database
     *
     * @return bool
     */
    private function checkTranslated(): bool
    {
        try {
            $select = $this->zdb->select(LEGALNOTICES_PREFIX . self::TABLE);
            $results = $this->zdb->execute($select);

            $existing = [];
            foreach ($results as $page) {
                if (
                    $page->body != ''
                    || $page->url != ''
                ) {
                    $existing[] = $page->id;
                }
            }

            if (count($existing) > 0) {
                $this->setTranslated($existing);
                return true;
            }
        } catch (Throwable $e) {
            Analog::log(
                'An error occurred checking translated pages.' . $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
        return false;
    }

    /**
     * Get specific page
     *
     * @param string $name Name of page to get
     * @param string $lang Language of page to get
     *
     * @return ArrayObject<string, int|string> of all page fields for one language.
     */
    public function getPages(string $name, string $lang): ArrayObject
    {
        /** @var I18n $i18n */
        global $i18n;

        // Check if language is set and exists
        $langs = $i18n->getList();
        $is_lang_ok = false;
        foreach ($langs as $l) {
            if ($lang === $l->getID()) {
                $is_lang_ok = true;
                break;
            }
        }

        if ($is_lang_ok !== true) {
            Analog::log(
                'Language ' . $lang .
                ' does not exists. Falling back to default Galette lang.',
                Analog::ERROR
            );
            $lang = $i18n->getID();
        }

        try {
            $select = $this->zdb->select(LEGALNOTICES_PREFIX . self::TABLE);
            $select->where(
                [
                    'name' => $name,
                    'lang' => $lang
                ]
            );
            $results = $this->zdb->execute($select);
            $page = $results->current();
            if ($page) {
                $this->current_page = $page;
            } else {
                // Page does not exist in the database, let's add it
                $default = null;
                $this->defaults = $this->getAllDefaults();
                foreach ($this->defaults as $d) {
                    if ($d['name'] == $name && $d['lang'] == $lang) {
                        $default = $d;
                        break;
                    }
                }
                if ($default !== null) {
                    $values = [
                        'name'        => $default['name'],
                        'body'        => $default['body'],
                        'url'         => $default['url'],
                        'lang'        => $default['lang'],
                        'label'       => $default['label'],
                        'last_update' => date('Y-m-d H:i:s')
                    ];

                    try {
                        $this->insert([$values]);
                        return $this->getPages($name, $lang);
                    } catch (Throwable $e) {
                        Analog::log(
                            'Unable to add missing requested page "' . $name .
                            ' (' . $lang . ') | ' . $e->getMessage(),
                            Analog::WARNING
                        );
                    }
                } else {
                    Analog::log(
                        'Unable to find missing requested page "' . $name .
                        ' (' . $lang . ')',
                        Analog::WARNING
                    );
                }
            }

            return $this->current_page;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get page `' . $name . '` for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Store page content
     *
     * @param string $name Page name to locate
     * @param string $lang Page language to locate
     * @param string $body Page body to store
     * @param string $url  Page external URL to store
     *
     * @return bool
     */
    public function storePageContent(string $name, string $lang, string $body, string $url): bool
    {
        try {
            // Clean body value from content left by summernote to apply
            // focus on the editor when empty.
            $body = $body == '<br>' || $body == '<p><br></p>' ? '' : $body;

            $values = [
                'body' => $body,
                'url' => $url,
                'last_update' => date('Y-m-d H:i:s')
            ];

            $update = $this->zdb->update(LEGALNOTICES_PREFIX . self::TABLE);
            $update->set($values)->where(
                [
                    'name'  => $name,
                    'lang' => $lang
                ]
            );
            $this->zdb->execute($update);

            return true;
        } catch (Throwable $e) {
            Analog::log(
                'An error has occurred while storing page content. | ' .
                $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Names List
     *
     * @param string $lang Requested language
     *
     * @return array<int,mixed> list of names used for pages
     */
    public function getNames(string $lang = I18n::DEFAULT_LANG): array
    {
        try {
            $select = $this->zdb->select(LEGALNOTICES_PREFIX . self::TABLE);
            $select->columns(
                array('name', 'label')
            )->where(array('lang' => $lang));

            $names = [];
            $results = $this->zdb->execute($select);
            foreach ($results as $result) {
                $names[] = $result;
            }
            return $names;
        } catch (Throwable $e) {
            Analog::log(
                'Cannot get pages names for lang `' . $lang . '` | ' .
                $e->getMessage(),
                Analog::WARNING
            );
            throw $e;
        }
    }

    /**
     * Get the page body, with all replacements done
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->proceedReplacements($this->current_page['body']);
    }

    /**
     * Insert values in database
     *
     * @param array<int, mixed> $values Values to insert
     *
     * @return void
     */
    private function insert(array $values): void
    {
        $insert = $this->zdb->insert(LEGALNOTICES_PREFIX . self::TABLE);
        $insert->values(
            array(
                'name'        => ':name',
                'body'        => ':body',
                'url'         => ':url',
                'lang'        => ':lang',
                'label'       => ':label',
                'last_update' => ':last_update'
            )
        );
        $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);

        foreach ($values as $value) {
            $stmt->execute($value);
        }
    }

    /**
     * Get default pages content for all languages
     *
     * @return array<int,mixed>
     */
    public function getAllDefaults(): array
    {
        /** @var I18n $i18n */
        global $i18n;

        $all = [];
        foreach (array_keys($i18n->getArrayList()) as $lang) {
            $all = array_merge($all, $this->getDefaultPages($lang));
        }

        return $all;
    }

    /**
     * Get default pages for specified language
     *
     * @param string $lang Requested lang. Defaults to en_US
     *
     * @return array<int,mixed>
     */
    public function getDefaultPages(string $lang): array
    {
        /** @var I18n $i18n */
        global $i18n;

        $current_lang = $i18n->getID();

        $i18n->changeLanguage($lang);

        $pages_fields = [
            [
                'name'        => 'legal-information',
                'body'        => '',
                'url'         => '',
                'lang'        => 'en_US',
                'label'       => _T("Legal Information", "legalnotices"),
                'last_update' => date('Y-m-d H:i:s')
            ],
            [
                'name'        => 'terms-of-service',
                'body'        => '',
                'url'         => '',
                'lang'        => 'en_US',
                'label'       => _T("Terms of Service", "legalnotices"),
                'last_update' => date('Y-m-d H:i:s')
            ],
            [
                'name'        => 'privacy-policy',
                'body'        => '',
                'url'         => '',
                'lang'        => 'en_US',
                'label'       => _T("Privacy Policy", "legalnotices"),
                'last_update' => date('Y-m-d H:i:s')
            ]
        ];

        $pages = [];

        foreach ($pages_fields as $page_fields) {
            $page_fields['lang'] = $lang;
            $pages[] = $page_fields;
        }

        // Reset to current lang
        $i18n->changeLanguage($current_lang);
        return $pages;
    }

    /**
     * Build legend array
     *
     * @return array<string, mixed>
     */
    public function getLegend(): array
    {
        $legend = $this->trait_getLegend();

        // Unset unnecessary patterns
        unset($legend['main']['patterns']['asso_logo']);
        unset($legend['main']['patterns']['asso_print_logo']);
        unset($legend['main']['patterns']['date_now']);
        unset($legend['main']['patterns']['login_uri']);
        unset($legend['main']['patterns']['asso_footer']);
        unset($legend['member']);

        $patterns = $this->getPagesPatterns(false);
        $legend['Pages'] = [
            'title'     => _T("Specific to the Legal Notices plugin", "legalnotices"),
            'patterns'  => $patterns
        ];

        return $legend;
    }

    /**
     * Set translated pages
     *
     * @param array<int, string> $pages array of translated pages
     *
     * @return void
     */
    public function setTranslated(array $pages): void
    {
        $this->translated = $pages;
    }

    /**
     * Check if the specified page is translated
     *
     * @param int $id page identifier
     *
     * @return boolean
     */
    public function isTranslated(int $id): bool
    {
        return in_array($id, $this->translated);
    }
}
