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
     * TwigExtension constructor.
     *
     * @param Session                $session
     * @param CmsRestClient          $cmsRestClient
     * @param ContentBlockService    $contentBlockService
     * @param CompositeObjectService $compositeObjectService
     * @param string                 $cmsBaseUrl
     * @param bool                   $editable
     */
    public function __construct(Session $session, CmsRestClient $cmsRestClient, ContentBlockService $contentBlockService, CompositeObjectService $compositeObjectService,  $cmsBaseUrl, $editable = false)
    {
        $this->session                = $session;
        $this->cmsRestClient          = $cmsRestClient;
        $this->contentBlockService    = $contentBlockService;
        $this->compositeObjectService = $compositeObjectService;
        $this->cmsBaseUrl             = $cmsBaseUrl;
        $this->editable               = $editable;
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
            new \Twig_SimpleFunction('cms_asset', [$this, 'cmsAsset']),
            new \Twig_SimpleFunction('spaceless', [$this, 'spaceless']),
        ];
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('content', [$this, 'content'])
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
        $compositeObjectService = $this->compositeObjectService;

        if ($this->isEditable()) {
            return $compositeObjectService->editable($id);
        }

        return '';
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
        return $this->cmsBaseUrl . '/' . $resource;
    }
}