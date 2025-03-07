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

namespace GaletteLegalNotices;

use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use Galette\Core\GalettePlugin;
use GaletteLegalNotices\Entity\Settings;

/**
 * Plugin Galette Legal Notices
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class PluginGaletteLegalnotices extends GalettePlugin
{
    /**
     * Extra menus entries
     *
     * @return array<string, string|array<string,mixed>>
     */
    public static function getMenusContents(): array
    {
        /** @var Login $login */
        global $login;
        /** @var Db $zdb */
        global $zdb;
        $settings = new Settings($zdb);
        $menus = [];
        $items = [];

        if ($login->isAdmin()) {
            $items[] = [
                'label' => _T("Settings"),
                'route' => [
                    'name' => 'legalnotices_settings'
                ]
            ];
        }

        if ($login->isAdmin() || $login->isStaff()) {
            $items[] = [
                'label' => _T("Pages content", "legalnotices"),
                'route' => [
                    'name' => 'legalnotices_pages'
                ]
            ];
        }

        if ($login->isAdmin() || $login->isStaff()) {
            $menus['plugin_legalnotices'] = [
                'title' => _T("Legal Notices", "legalnotices"),
                'icon' => 'balance scale',
                'items' => $items
            ];
        }

        return $menus;
    }

    /**
     * Extra public menus entries
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getPublicMenusItemsList(): array
    {
        /** @var Db $zdb */
        global $zdb;
        $settings = new Settings($zdb);
        $items = [];
        $children = [];

        if ($settings->enable_legal_information) {
            $children[] = [
                'label' => _T("Legal Information", "legalnotices"),
                'route' => [
                    'name' => 'legalnotices_page',
                    'args' => ['name' => 'legal-information']
                ],
                'icon' => 'balance scale left'
            ];
        }
        if ($settings->enable_terms_of_service) {
            $children[] = [
                'label' => _T("Terms of Service", "legalnotices"),
                'route' => [
                    'name' => 'legalnotices_page',
                    'args' => ['name' => 'terms-of-service']
                ],
                'icon' => 'handshake outline'
            ];
        }
        if ($settings->enable_privacy_policy) {
            $children[] = [
                'label' => _T("Privacy Policy", "legalnotices"),
                'route' => [
                    'name' => 'legalnotices_page',
                    'args' => ['name' => 'privacy-policy']

                ],
                'icon' => 'lock'
            ];
        }

        if ($settings->publicpage_links) {
            if (count($children) > 1) {
                $items[] = [
                    'label' => _T("Legal Notices", "legalnotices"),
                    'icon' => 'balance scale',
                    'children' => $children
                ];
            } else {
                $items = array_merge($items, $children);
            }
        }

        return $items;
    }

    /**
     * Get dashboards contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDashboardsContents(): array
    {
        return [];
    }

    /**
     * Get actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getListActionsContents(Adherent $member): array
    {
        return [];
    }

    /**
     * Get detailed actions contents
     *
     * @param Adherent $member Member instance
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getDetailedActionsContents(Adherent $member): array
    {
        return static::getListActionsContents($member);
    }

    /**
     * Get batch actions contents
     *
     * @return array<int, string|array<string,mixed>>
     */
    public static function getBatchActionsContents(): array
    {
        return [];
    }

    /**
     * Get plugin settings
     *
     * @return array<string>
     */
    public static function getPluginSettings(): array
    {
        /** @var Db $zdb */
        global $zdb;
        $settings = new Settings($zdb);
        return $settings->getSettings();
    }
}
