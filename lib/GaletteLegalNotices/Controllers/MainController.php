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

namespace GaletteLegalNotices\Controllers;

use DI\Attribute\Inject;
use Galette\Core\I18n;
use Galette\Controllers\AbstractPluginController;
use GaletteLegalNotices\Entity\Pages;
use GaletteLegalNotices\Entity\Settings;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Galette Legal Notices main controller
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Guillaume AGNIERAY <dev@agnieray.net>
 */

class MainController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette Legal Notices")]
    protected array $module_info;

    /**
     * List pages
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     * @param ?string  $lang     Language
     * @param ?string  $name     Page name
     *
     * @return Response
     */
    public function listPages(Request $request, Response $response, ?string $lang = null, ?string $name = null): Response
    {
        if ($lang === null) {
            $lang = $this->preferences->pref_lang;
        }
        if ($name === null) {
            $name = Pages::DEFAULT_NAME;
        }

        $pages = new Pages($this->zdb);

        $page = $pages->getPages($name, $lang);

        // Display page
        $this->view->render(
            $response,
            $this->getTemplate('legalnotices_pages'),
            [
                'page_title'        => _T("Pages content", "legalnotices"),
                'pages'             => $pages,
                'pageslist'         => $pages->getNames($lang),
                'langlist'          => $this->i18n->getList(),
                'cur_lang'          => $lang,
                'cur_lang_name'     => $this->i18n->getNameFromId($lang),
                'cur_name'          => $name,
                'page'              => $page,
                'html_editor'       => true
            ]
        );
        return $response;
    }

    /**
     * Change page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function changePage(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'legalnotices_pages',
                    [
                        'lang'  => $post['sel_lang'],
                        'name'   => $post['sel_page']
                    ]
                )
            );
    }

    /**
     * Edit page
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function editPage(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $pages = new Pages($this->zdb);

        $cur_lang = $post['cur_lang'];
        $cur_name = $post['cur_name'];

        $page = $pages->getPages($cur_name, $cur_lang);
        $store = $pages->storePageContent(
            $cur_name,
            $cur_lang,
            $post['page_body'],
            $post['external_url']
        );

        if (!$store) {
            $this->flash->addMessage(
                'error_detected',
                preg_replace(
                    '(%s)',
                    $page['label'],
                    _T('The "%s" page has not been modified!', "legalnotices")
                )
            );
        } else {
            $this->flash->addMessage(
                'success_detected',
                preg_replace(
                    '(%s)',
                    $page['label'],
                    _T('The "%s" page has been successfully modified.', "legalnotices")
                )
            );
        }

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor(
                    'legalnotices_pages',
                    [
                        'lang'  => $cur_lang,
                        'name'   => $cur_name
                    ]
                )
            );
    }

    /**
     * View page
     *
     * @param Request     $request  PSR Request
     * @param Response    $response PSR Response
     * @param string|null $name     One of 'legal-information', 'terms-of-service' or 'privacy-policy'
     *
     * @return Response
     */
    public function viewPage(Request $request, Response $response, ?string $name = null): Response
    {
        /** @var I18n $i18n */
        global $i18n;
        $lang = $i18n->getID();
        $login = $this->login;
        $pages = new Pages($this->zdb);
        $plugin_settings = new Settings($this->zdb);
        $translated = true;

        $page = $pages->getPages($name, $lang);

        // Get fallback language if page is not translated
        $lang = $plugin_settings->getFallbackLanguage();
        if (!$pages->isTranslated($page['id']) && $page['lang'] != $lang) {
            $translated = false;
            $page = $pages->getPages($name, $lang);
        }

        $last_update = new \DateTime($page['last_update']);

        $params = [
            'name'          => $page['name'],
            'page_title'    => $page['label'],
            'body'          => $page['body'],
            'last_update'   => $last_update->format(_T('Y-m-d')),
            'translated'    => $translated
        ];

        if (!$login->isLogged()) {
            $params['is_public'] = true;
        }

        // Prevent page access if not enabled in settings
        if (!$plugin_settings->isPageEnabled($page['name'])) {
            // return $response->withStatus(404); // Doesn't work :(
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/page-not-found');
        }

        // Redirect to external url if one is set
        if ($page['url'] != '') {
            return $response
                ->withStatus(301)
                ->withHeader('Location', $page['url']);
        }

        // Display page
        $this->view->render(
            $response,
            $this->getTemplate('legalnotices_page'),
            $params
        );
        return $response;
    }

    /**
     * Settings
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function settings(Request $request, Response $response): Response
    {
        $plugin_settings = new Settings($this->zdb);
        $settings_fields = $plugin_settings->getFieldsNames();
        $settings = [];

        if ($this->session->entered_settings) {
            $settings = $this->session->entered_settings;
            $this->session->entered_settings = null;
        } else {
            foreach ($settings_fields as $fieldname) {
                $settings[$fieldname] = $plugin_settings->$fieldname;
            }
        }

        $params = [
            'page_title' => _T("Legal Notices settings", "legalnotices"),
            'settings' => $settings,
            'langlist' => $this->i18n->getList(),
        ];

        // Display page
        $this->view->render(
            $response,
            $this->getTemplate('legalnotices_settings'),
            $params
        );
        return $response;
    }

    /**
     * Store Settings
     *
     * @param Request  $request  PSR Request
     * @param Response $response PSR Response
     *
     * @return Response
     */
    public function storeSettings(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        $plugin_settings = new Settings($this->zdb);
        $settings_fields = $plugin_settings->getFieldsNames();
        $error_detected = [];

        if ($this->login->isAdmin()) {
            $plugin_settings->check($post);
            foreach ($settings_fields as $fieldname) {
                if (isset($post[$fieldname])) {
                    $plugin_settings->$fieldname = $post[$fieldname];
                }
            }
            $stored = $plugin_settings->store();
            if ($stored) {
                $this->flash->addMessage(
                    'success_detected',
                    _T("Legal Notices settings have been saved.", "legalnotices")
                );
            } else {
                $error_detected[] = _T("An SQL error has occurred while storing Legal Notices settings. Please try again, and contact the administrator if the problem persists.", "legalnotices");
            }
        }

        if (count($error_detected) > 0) {
            $this->session->entered_settings = $post;
            foreach ($error_detected as $error) {
                $this->flash->addMessage(
                    'error_detected',
                    $error
                );
            }
        }

        return $response
            ->withStatus(301)
            ->withHeader('Location', $this->routeparser->urlFor('legalnotices_settings'));
    }
}
