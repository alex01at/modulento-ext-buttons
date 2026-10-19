<?php

declare(strict_types=1);

namespace Modulento\Buttons;

use Modulento\Core\Extension\Extension as ExtensionContract;
use Modulento\Core\Extension\Registrar;
use Modulento\Core\Support\Router;

/**
 * A public, stateless tool: lay out uploaded images in 57mm circles and
 * export a print-ready A4 PDF for a button maker. Nothing is persisted -
 * every request starts from an empty page, so this addon needs no
 * migration and no database table.
 */
final class Extension implements ExtensionContract
{
    public function register(Registrar $registrar): void
    {
        $registrar->routes(function (Router $router): void {
            $router->get('/buttons', [ButtonsController::class, 'index'], Router::PUBLIC);
            $router->post('/buttons/export', [ButtonsController::class, 'export'], Router::PUBLIC);
        });

        $registrar->footerLink('buttons.footer_link', '/buttons');
    }
}
