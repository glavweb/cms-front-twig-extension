<?php

/*
 * This file is part of the GLAVWEB.cms CmsTwigExtension package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\CmsTwigExtension\Extension;

use Glavweb\CmsContentBlock\Service\OptionService;
use Glavweb\CmsContentBlock\Service\OptionServiceService;
use Symfony\Component\HttpFoundation\Session\Session;
use Glavweb\CmsRestClient\CmsRestClient;
use Glavweb\CmsContentBlock\Service\ContentBlockService;
use Glavweb\CmsCompositeObject\Service\CompositeObjectService;

/**
 * Class CmsTwigExtension
 *
 * @package Glavweb\CmsTwigExtension
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class CmsTwigExtension extends \Twig_Extension
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var CmsRestClient
     */
    private $cmsRestClient;

    /**
     * @var ContentBlockService
     */
    private $contentBlockService;

    /**
     * @var OptionService
     */
    private $optionService;

    /**
     * @var CompositeObjectService
     */
    private $compositeObjectService;

    /**
     * @var string
     */
    private $cmsBaseUrl;

    /**
     * @var bool
     */
    private $editable;

    /**
     * @var bool
     */
    private $markupMode;

    /**
     * @var
     */
    private $apiBaseUrl;

    /**
     * TwigExtension constructor.
     *
     * @param Session                $session
     * @param CmsRestClient          $cmsRestClient
     * @param ContentBlockService    $contentBlockService
     * @param OptionService          $optionService
     * @param CompositeObjectService $compositeObjectService
     * @param string                 $cmsBaseUrl
     * @param bool                   $editable
     * @param bool                   $markupMode
     */
    public function __construct(Session $session, CmsRestClient $cmsRestClient, ContentBlockService $contentBlockService, OptionService $optionService, CompositeObjectService $compositeObjectService,  $cmsBaseUrl, $apiBaseUrl, $editable = false, $markupMode = false)
    {
        $this->session                = $session;
        $this->cmsRestClient          = $cmsRestClient;
        $this->contentBlockService    = $contentBlockService;
        $this->optionService          = $optionService;
        $this->compositeObjectService = $compositeObjectService;
        $this->cmsBaseUrl             = $cmsBaseUrl;
        $this->apiBaseUrl             = $apiBaseUrl;
        $this->editable               = $editable;
        $this->markupMode             = $markupMode;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get', [$this, 'getJson']),
            new \Twig_SimpleFunction('content', [$this, 'content']),
            new \Twig_SimpleFunction('editable', [$this, 'editable'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('editable_object', [$this, 'editableObject'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('objects', [$this, 'getObjects']),
            new \Twig_SimpleFunction('option', [$this, 'option']),
            new \Twig_SimpleFunction('cms_asset', [$this, 'cmsAsset']),
            new \Twig_SimpleFunction('cms_object_url', [$this, 'cmsObjectUrl']),
            new \Twig_SimpleFunction('spaceless', [$this, 'spaceless']),
        ];
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('content', [$this, 'content']),
            new \Twig_SimpleFilter('filter', [$this, 'listFilter'])
        ];
    }

    /**
     * @param string $url
     * @return array
     */
    public function getJson($url)
    {
        $cmsRestClient = $this->cmsRestClient;

        $response = $cmsRestClient->get($url);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get content block
     *
     * @param string $category
     * @param string $blockName
     * @param string $default
     * @return string
     */
    public function content($category, $blockName, $default = null)
    {
        if ($this->markupMode) {
            return $default;
        }

        $contentBlockService = $this->contentBlockService;

        return $contentBlockService->getContentBlock($category, $blockName, $default);
    }

    /**
     * @param string $category
     * @param string $blockName
     * @return string
     */
    public function editable($category, $blockName)
    {
        if ($this->markupMode) {
            return '';
        }

        $contentBlockService = $this->contentBlockService;

        if ($this->isEditable()) {
            return $contentBlockService->editable($category, $blockName);
        }

        return '';
    }

    /**
     * Editable object
     *
     * @param int $id
     * @return string
     */
    public function editableObject($id)
    {
        if ($this->markupMode) {
            return '';
        }

        $compositeObjectService = $this->compositeObjectService;

        if ($this->isEditable()) {
            return $compositeObjectService->editable($id);
        }

        return '';
    }

    /**
     * Get option
     *
     * @param string $category
     * @param string $optionName
     * @param string $default
     * @return string
     */
    public function option($category, $optionName, $default = null)
    {
        if ($this->markupMode) {
            return $default;
        }

        $optionService = $this->optionService;

        return $optionService->getOption($category, $optionName, $default);
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        $session       = $this->session;
        $cmsRestClient = $this->cmsRestClient;

        return
            $this->editable &&
            $session->has('api_token') &&
            $cmsRestClient->validateToken($session->get('api_token'))
            ;
    }

    /**
     * Get composite objects
     *
     * @param string $className
     * @return array
     */
    public function getObjects($className)
    {
        return $this->compositeObjectService->getObjectsByClassName($className);
    }

    /**
     * Spaceless
     *
     * @param string $value
     * @return string
     */
    public function spaceless($value)
    {
        return trim(preg_replace('/>\s+</', '><', $value));
    }

    /**
     * CMS asset
     *
     * @param string $resource
     * @return string
     */
    public function cmsAsset($resource)
    {
        if ($this->markupMode) {
            return '/' . $resource;
        }

        return $this->cmsBaseUrl . '/' . $resource;
    }

    /**
     * CMS object URL
     *
     * @param string $className
     * @return string
     */
    public function cmsObjectUrl($className)
    {
        return $this->apiBaseUrl . '/composite-object/objects?className=' . $className;
    }

    /**
     * @param array $list
     * @param array $filters
     * @return array
     */
    public function listFilter(array $list, array $filters)
    {
        return array_filter($list, function ($item) use ($filters) {
            foreach ($filters as $filter) {
                foreach ($filter as $filterName => $filterValue) {
                    if (!isset($item[$filterName]) || $item[$filterName] != $filterValue) {
                        return false;
                    }

                    return true;
                }
            }
        });
    }
}
