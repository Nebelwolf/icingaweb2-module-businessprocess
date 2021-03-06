<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Date\DateFormatter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Html;
use Icinga\Module\Businessprocess\Html\HtmlString;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Url;

abstract class Renderer extends Html
{
    /** @var BpConfig */
    protected $config;

    /** @var BpNode */
    protected $parent;

    /** @var bool Administrative actions are hidden unless unlocked */
    protected $locked = true;

    /** @var Url */
    protected $url;

    /** @var Url */
    protected $baseUrl;

    /** @var array */
    protected $path = array();

    /** @var bool */
    protected $isBreadcrumb = false;

    /**
     * Renderer constructor.
     *
     * @param BpConfig $config
     * @param BpNode|null $parent
     */
    public function __construct(BpConfig $config, BpNode $parent = null)
    {
        $this->config = $config;
        $this->parent = $parent;
    }

    /**
     * @return BpConfig
     */
    public function getBusinessProcess()
    {
        return $this->config;
    }

    /**
     * Whether this will render all root nodes
     *
     * @return bool
     */
    public function wantsRootNodes()
    {
        return $this->parent === null;
    }

    /**
     * Whether this will only render parts of given config
     *
     * @return bool
     */
    public function rendersSubNode()
    {
        return $this->parent !== null;
    }

    /**
     * @return BpNode
     */
    public function getParentNode()
    {
        return $this->parent;
    }

    /**
     * @return BpNode[]
     */
    public function getParentNodes()
    {
        if ($this->wantsRootNodes()) {
            return array();
        }

        return $this->parent->getParents();
    }

    /**
     * @return BpNode[]
     */
    public function getChildNodes()
    {
        if ($this->wantsRootNodes()) {
            return $this->config->getRootNodes();
        } else {
            return $this->parent->getChildren();
        }
    }

    /**
     * @return int
     */
    public function countChildNodes()
    {
        if ($this->wantsRootNodes()) {
            return $this->config->countChildren();
        } else {
            return $this->parent->countChildren();
        }
    }

    /**
     * @param $summary
     * @return Container
     */
    public function renderStateBadges($summary)
    {
        $container = Container::create(
            array('class' => 'badges')
        )/* ->renderIfEmpty(false) */;

        foreach ($summary as $state => $cnt) {
            if ($cnt === 0
                || $state === 'OK'
                || $state === 'UP'
            ) {
                continue;
            }

            $container->addContent(
                Element::create(
                    'span',
                    array(
                        'class' => array(
                            'badge',
                            'badge-' . strtolower($state)
                        ),
                        // TODO: We should translate this in this module
                        'title' => mt('monitoring', $state)
                    )
                )->setContent($cnt)
            );
        }

        return $container;
    }

    public function getNodeClasses(Node $node)
    {
        if ($node->isMissing()) {
            $classes = array('missing');
        } else {
            $classes = array(
                strtolower($node->getStateName())
            );
            if ($node->hasMissingChildren()) {
                $classes[] = 'missing-children';
            }
        }

        if ($node->isHandled()) {
            $classes[] = 'handled';
        }

        if ($node instanceof BpNode) {
            $classes[] = 'process-node';
        } else {
            $classes[] = 'monitored-node';
        }
        // TODO: problem?
        return $classes;
    }

    public function setPath(array $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getCurrentPath()
    {
        $path = $this->getPath();
        if ($this->rendersSubNode()) {
            $path[] = (string) $this->parent;
        }
        return $path;
    }

    /**
     * @param Url $url
     * @return $this
     */
    public function setUrl(Url $url)
    {
        $this->url = $url->without(array(
            'deletenode',
            'deleteparent',
            'editnode',
            'simulationnode',
            'view'
        ));
        $this->setBaseUrl($url);
        return $this;
    }

    /**
     * @param Url $url
     * @return $this
     */
    public function setBaseUrl(Url $url)
    {
        $this->baseUrl = $url->without(array('node', 'path', 'view'));
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return Url
     * @throws ProgrammingError
     */
    public function getBaseUrl()
    {
        if ($this->baseUrl === null) {
            throw new ProgrammingError('Renderer has no baseUrl');
        }

        return clone($this->baseUrl);
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @return $this
     */
    public function lock()
    {
        $this->locked = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function unlock()
    {
        $this->locked = false;
        return $this;
    }

    /**
     * TODO: Get rid of this
     *
     * @return $this
     */
    public function setIsBreadcrumb()
    {
        $this->isBreadcrumb = true;
        return $this;
    }

    public function isBreadcrumb()
    {
        return $this->isBreadcrumb;
    }

    public function timeSince($time, $timeOnly = false)
    {
        if (! $time) {
            return HtmlString::create('');
        }

        return Element::create(
            'span',
            array(
                'class' => array('relative-time', 'time-since'),
                'title' => DateFormatter::formatDateTime($time),
            )
        )->setContent(DateFormatter::timeSince($time, $timeOnly));
    }

    protected function createUnboundParent(BpConfig $bp)
    {
        return $bp->getNode('__unbound__');
    }

    /**
     * Just to be on the safe side
     */
    public function __destruct()
    {
        unset($this->parent);
        unset($this->config);
    }
}
