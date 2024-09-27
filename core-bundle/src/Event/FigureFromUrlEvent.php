<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Event;

use Contao\CoreBundle\Image\Studio\Figure;
use Psr\Http\Message\UriInterface;
use Symfony\Contracts\EventDispatcher\Event;

class FigureFromUrlEvent extends Event
{
    private Figure|null $figure = null;

    public function __construct(private UriInterface $uri)
    {
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function getFigure(): Figure|null
    {
        return $this->figure;
    }

    public function setFigure(Figure $figure): void
    {
        $this->figure = $figure;

        $this->stopPropagation();
    }
}
