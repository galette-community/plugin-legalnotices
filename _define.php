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

$this->register(
    'Galette Legal Notices',
    'Manage legal notices in Galette',
    'Guillaume AGNIERAY',
    '1.0.0',
    '1.2.0',
    'legalnotices',
    '2025-10-17',
    [
        'legalnotices_settings' => 'admin',
        'legalnotices_store_settings' => 'admin',
        'legalnotices_pages' => 'staff',
        'legalnotices_page_change' => 'staff',
        'legalnotices_page_edit' => 'staff'
    ],
    9999
);
