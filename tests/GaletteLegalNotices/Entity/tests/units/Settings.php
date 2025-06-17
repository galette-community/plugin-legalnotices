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

/**
 * Settings tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */
class Settings extends GaletteTestCase
{
    /**
     * Cleanup after each test method
     *
     * @return void
     */
    public function tearDown(): void
    {
        $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . \GaletteLegalNotices\Entity\Settings::TABLE);
        $this->zdb->execute($delete);
        parent::tearDown();
    }

    /**
     * Test preferences initialization
     *
     * @return void
     */
    public function testInstallInit(): void
    {
        $settings = new \GaletteLegalNotices\Entity\Settings($this->zdb);
        $result = $settings->installInit();
        $this->assertTrue($result);

        foreach ($settings->getDefaults() as $key => $expected) {
            $value = $settings->$key;

            switch ($key) {
                case 'fallback_language':
                    $this->assertSame('en_US', $value);
                    break;
                default:
                    $this->assertEquals($expected, $value, 'Wrong value for ' . $key);
                    break;
            }
        }

        //try to set and get a non existent value
        $settings->doesnotexist = 'that *does* not exist.';
        $this->expectLogEntry(
            \Analog::WARNING,
            'Trying to set a Legal Notices setting value which does not seem to exist (doesnotexist)'
        );
        $false_result = $settings->doesnotexist;
        $this->assertFalse($false_result);

        //change cookie lifetime
        $cookie_expiration = 360;
        $settings->cookie_expiration = $cookie_expiration;
        $check = $settings->cookie_expiration;
        $this->assertSame($cookie_expiration, $check);

        $cookie_expiration = $settings->cookie_expiration;
        $this->assertEquals(360, $cookie_expiration);

        $cookie_expiration = 180;
        $settings->cookie_expiration = $cookie_expiration;
        $result = $settings->store();
        $this->assertTrue($result);

        $check_cookie_expiration = $settings->cookie_expiration;
        $this->assertEquals($cookie_expiration, $check_cookie_expiration);

        //reset database value...
        $settings->cookie_expiration = 90;
        $settings->store();
    }

    /**
     * Test fields names
     *
     * @return void
     */
    public function testFieldsNames(): void
    {
        $settings = new \GaletteLegalNotices\Entity\Settings($this->zdb);
        $settings->load();
        $fields_names = $settings->getFieldsNames();
        $expected = array_keys($settings->getDefaults());

        sort($fields_names);
        sort($expected);

        $this->assertSame($expected, $fields_names);
    }

    /**
     * Test settings updating when some are missing
     *
     * @return void
     */
    public function testUpdate(): void
    {
        $settings = new \GaletteLegalNotices\Entity\Settings($this->zdb);

        $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . \GaletteLegalNotices\Entity\Settings::TABLE);
        $delete->where(
            [
                \GaletteLegalNotices\Entity\Settings::PK => 'cookie_expiration'
            ]
        );
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(LEGALNOTICES_PREFIX . \GaletteLegalNotices\Entity\Settings::TABLE);
        $delete->where(
            [
                \GaletteLegalNotices\Entity\Settings::PK => 'cookie_domain'
            ]
        );
        $this->zdb->execute($delete);

        $settings->load();
        $cookie_expiration = $settings->cookie_expiration;
        $cookie_domain = $settings->cookie_domain;

        $this->assertFalse($cookie_expiration);
        $this->assertFalse($cookie_domain);

        $settings = new \GaletteLegalNotices\Entity\Settings($this->zdb);
        $cookie_expiration = $settings->cookie_expiration;
        $cookie_domain = $settings->cookie_domain;

        $this->assertSame(90, $cookie_expiration);
        $this->assertSame('', $cookie_domain);
    }

    /**
     * Test __isset
     *
     * @return void
     */
    public function testIsset(): void
    {
        $settings = new \GaletteLegalNotices\Entity\Settings($this->zdb);

        $this->assertFalse(isset($settings->defaults));
        $this->assertFalse(isset($settings->doesnotexist));
        $this->assertTrue(isset($settings->enable_legal_information));
        $this->assertTrue(isset($settings->cookie_domain));
    }
}
