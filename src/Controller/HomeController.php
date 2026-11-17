<?php

declare(strict_types=1);

namespace Buzlook\Controller;

use Buzlook\FreezerSession;
use Buzlook\View;

/**
 * Handles the landing page.
 */
final class HomeController
{
    public function __construct(
        private readonly FreezerSession $session,
        private readonly View $view,
    ) {
    }

    public function show(): void
    {
        $this->view->render('home', ['title' => 'Buzlook', 'freezer' => $this->session->currentFreezer()]);
    }
}
