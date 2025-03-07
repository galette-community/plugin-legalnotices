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

use GaletteLegalNotices\Controllers\MainController;

//Constants and classes from plugin
require_once $module['root'] . '/_config.inc.php';

$app->get(
    '/settings',
    [MainController::class, 'settings']
)->setName('legalnotices_settings')->add($authenticate);

$app->post(
    '/settings',
    [MainController::class, 'storeSettings']
)->setName('legalnotices_store_settings')->add($authenticate);

$app->get(
    '/pages[/{lang}/{name}]',
    [MainController::class, 'listPages']
)->setName('legalnotices_pages')->add($authenticate);

$app->post(
    '/pages/change',
    [MainController::class, 'changePage']
)->setName('legalnotices_page_change')->add($authenticate);

$app->post(
    '/pages',
    [MainController::class, 'editPage']
)->setName('legalnotices_page_edit')->add($authenticate);

$app->get(
    '/{name:legal-information|terms-of-service|privacy-policy}',
    [MainController::class, 'viewPage']
)->setName('legalnotices_page');
