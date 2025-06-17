<?php

/**
 * Copyright © 2003-2025 The Galette Team
 *
 * This file is part of Galette Legal Notices plugin (https://galette-community.github.io/plugin-legalnotices).
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

namespace GaletteLegalNotices\Entity\tests\units;

use Galette\GaletteTestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Pages tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */
class Pages extends GaletteTestCase
{
    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . \GaletteLegalNotices\Entity\Pages::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList(): void
    {
        $count_pages = 3;
        $pages = new \GaletteLegalNotices\Entity\Pages(
            $this->preferences
        );
        $pages->installInit();

        $list = $pages->getDefaultPages(\Galette\Core\I18n::DEFAULT_LANG);
        $this->assertCount($count_pages, $list);

        foreach (array_keys($this->i18n->getArrayList()) as $lang) {
            $list = $pages->getDefaultPages($lang);
            $this->assertCount($count_pages, $list);
        }

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(LEGALNOTICES_PREFIX . $pages::TABLE, $pages::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual($count_pages, $result->last_value, 'Incorrect texts sequence ' . $result->last_value);

            $this->zdb->db->query(
                'SELECT setval(\'' . $this->zdb->getSequenceName(LEGALNOTICES_PREFIX . $pages::TABLE, $pages::PK, true) . '\', 1)',
                Adapter::QUERY_MODE_EXECUTE
            );
        }

        //reinstall pages
        $pages->installInit(false);

        $list = $pages->getNames(\Galette\Core\I18n::DEFAULT_LANG);
        $this->assertCount($count_pages, $list);

        if ($this->zdb->isPostgres()) {
            $select = $this->zdb->select($this->zdb->getSequenceName(LEGALNOTICES_PREFIX . $pages::TABLE, $pages::PK));
            $select->columns(['last_value']);
            $results = $this->zdb->execute($select);
            $result = $results->current();
            $this->assertGreaterThanOrEqual(2, $result->last_value, 'Incorrect texts sequence ' . $result->last_value);
        }
    }

    /**
     * Test expected patterns
     *
     * @return void
     */
    public function testExpectedPatterns(): void
    {
        $pages = new \GaletteLegalNotices\Entity\Pages(
            $this->preferences
        );

        $pages_expected = [
            'asso_name' => '/{ASSO_NAME}/',
            'asso_slogan' => '/{ASSO_SLOGAN}/',
            'asso_address' => '/{ASSO_ADDRESS}/',
            'asso_address_multi' => '/{ASSO_ADDRESS_MULTI}/',
            'asso_phone_number' => '/{ASSO_PHONE}/',
            'asso_email' => '/{ASSO_EMAIL}/',
            'asso_website' => '/{ASSO_WEBSITE}/',
            'asso_logo' => '/{ASSO_LOGO}/',
            'asso_print_logo' => '/{ASSO_PRINT_LOGO}/',
            'date_now' => '/{DATE_NOW}/',
            'login_uri' => '/{LOGIN_URI}/',
            'asso_footer' => '/{ASSO_FOOTER}/',
            'asso_phone_link' => '/{ASSO_PHONE_LINK}/',
            'asso_email_link' => '/{ASSO_EMAIL_LINK}/'
        ];
        $this->assertSame($pages_expected, $pages->getPatterns());
    }

    /**
     * Test page replacements
     *
     * @return void
     */
    public function testReplacements(): void
    {
        $page_body = '{ASSO_NAME} | {ASSO_PHONE_LINK} | {ASSO_EMAIL_LINK}';
        $this->preferences->pref_org_email = 'contact@galette.eu';
        $pages = new \GaletteLegalNotices\Entity\Pages(
            $this->preferences
        );

        $pages->storePageContent('legal-information', 'en_US', $page_body, '');
        $pages->getPages('legal-information', 'en_US');
        $this->assertSame(
            'Galette | ' .
            ' | ' .
            '<span class="obfuscate"><span class="u">contact</span> [at] <span class="d">galette<span class="p"> [dot] </span>eu</span></span>',
            $pages->getBody()
        );

        $this->preferences->pref_org_phone_number = '+00 0 00 00 00 00';
        $pages = new \GaletteLegalNotices\Entity\Pages(
            $this->preferences
        );
        $pages->getPages('legal-information', 'en_US');
        $this->assertSame(
            'Galette | ' .
            '<a href="tel:+00000000000">+00 0 00 00 00 00</a> | ' .
            '<span class="obfuscate"><span class="u">contact</span> [at] <span class="d">galette<span class="p"> [dot] </span>eu</span></span>',
            $pages->getBody()
        );

        $legend = $pages->getLegend();
        $this->assertCount(2, $legend);
        $this->assertArrayHasKey('main', $legend);
        $this->assertArrayHasKey('pages', $legend);

        $this->assertCount(7, $legend['main']['patterns']);
        $this->assertCount(2, $legend['pages']['patterns']);
    }
}
