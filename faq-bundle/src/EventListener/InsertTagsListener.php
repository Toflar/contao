<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\FaqBundle\EventListener;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Contao\PageModel;

/**
 * Handles FAQ insert tags.
 *
 * @author Andreas Schempp <https://github.com/aschempp>
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class InsertTagsListener
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * @var array
     */
    private $supportedTags = [
        'faq',
        'faq_open',
        'faq_url',
        'faq_title',
    ];

    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Replaces FAQ insert tags.
     *
     * @param string $tag
     *
     * @return string|false
     */
    public function onReplaceInsertTags($tag)
    {
        $elements = explode('::', $tag);
        $key = strtolower($elements[0]);

        if (!in_array($key, $this->supportedTags, true)) {
            return false;
        }

        $this->framework->initialize();

        /** @var FaqModel $adapter */
        $adapter = $this->framework->getAdapter('Contao\FaqModel');

        $faq = $adapter->findByIdOrAlias($elements[1]);

        if (null === $faq || false === ($url = $this->generateUrl($faq))) {
            return '';
        }

        return $this->generateReplacement($faq, $key, $url);
    }

    /**
     * Generates the URL for an FAQ.
     *
     * @param FaqModel $faq
     *
     * @return string|false
     */
    private function generateUrl(FaqModel $faq)
    {
        /** @var PageModel $jumpTo */
        if (!(($category = $faq->getRelated('pid')) instanceof FaqCategoryModel)
            || !(($jumpTo = $category->getRelated('jumpTo')) instanceof PageModel)
        ) {
            return false;
        }

        /** @var Config $config */
        $config = $this->framework->getAdapter('Contao\Config');

        return $jumpTo->getFrontendUrl(($config->get('useAutoItem') ? '/' : '/items/').($faq->alias ?: $faq->id));
    }

    /**
     * Generates the replacement string.
     *
     * @param FaqModel $faq
     * @param string   $key
     * @param string   $url
     *
     * @return string|false
     */
    private function generateReplacement(FaqModel $faq, $key, $url)
    {
        switch ($key) {
            case 'faq':
                return sprintf(
                    '<a href="%s" title="%s">%s</a>',
                    $url,
                    $this->specialchars($faq->question),
                    $faq->question
                );

            case 'faq_open':
                return sprintf(
                    '<a href="%s" title="%s">',
                    $url,
                    $this->specialchars($faq->question)
                );

            case 'faq_url':
                return $url;

            case 'faq_title':
                return $this->specialchars($faq->question);
        }

        return false;
    }

    /**
     * Converts special characters to HTML entities preventing double encodings.
     *
     * @param string $str
     *
     * @return string
     */
    private function specialchars($str)
    {
        /** @var Config $config */
        $config = $this->framework->getAdapter('Contao\Config');

        return htmlspecialchars($str, ENT_COMPAT, $config->get('characterSet'), false);
    }
}
