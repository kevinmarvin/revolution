<?php
/**
 * A derivative of modResource that stores content on the filesystem.
 *
 * {@inheritdoc}
 *
 * @package modx
 */
class modStaticResource extends modResource {
    /**
     * @var string Path of the file containing the source content, relative to
     * the {@link modStaticResource::$_sourcePath}.
     */
    protected $_sourceFile= '';
    /**
     * @var integer Size of the source file content in bytes.
     */
    protected $_sourceFileSize= 0;
    /**
     * @var string An absolute base filesystem path where the source file
     * exists.
     */
    protected $_sourcePath= '';

    function __construct(& $xpdo) {
        parent :: __construct($xpdo);
        $this->set('class_key','modStaticResource');
    }

    /**
     * Get the absolute path to the static source file represented by this instance.
     *
     * @param array $options An array of options.
     * @return string The absolute path to the static source file.
     */
    public function getSourceFile(array $options = array()) {
        if (empty($this->_sourceFile)) {
            $filename = parent :: getContent($options);
            if (!empty($filename)) {
                $array = array();

                if ($this->xpdo->getParser() && $this->xpdo->parser->collectElementTags($filename, $array)) {
                    $this->xpdo->parser->processElementTags('', $filename);
                }
            }

            if (!file_exists($filename)) {
                $this->_sourcePath= $this->xpdo->getOption('resource_static_path', $options, $this->xpdo->getOption('base_path'));
                if ($this->xpdo->getParser() && $this->xpdo->parser->collectElementTags($this->_sourcePath, $array)) {
                    $this->xpdo->parser->processElementTags('', $this->_sourcePath);
                }
                $this->_sourceFile= $this->_sourcePath . $filename;
            } else {
                $this->_sourceFile= $filename;
            }
        }
        return $this->_sourceFile;
    }

    /**
     * Get the filesize of the static source file represented by this instance.
     *
     * @param array $options An array of options.
     * @return integer The filesize of the source file in bytes.
     */
    public function getSourceFileSize(array $options = array()) {
        if (empty($this->_sourceFileSize)) {
            $this->getSourceFile($options);
            if (file_exists($this->_sourceFile)) {
                $this->_sourceFileSize = filesize($this->_sourceFile);
            }
        }
        return $this->_sourceFileSize;
    }

    /**
     * Treats the local content as a filename to load the raw content from.
     *
     * {@inheritdoc}
     */
    public function getContent(array $options = array()) {
        $content = '';
        $this->getSourceFile($options);
        if (!empty ($this->_sourceFile)) {
            if (file_exists($this->_sourceFile)) {
                $content= $this->getFileContent($this->_sourceFile);
                if ($content === false) {
                    $content = '';
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "No content could be retrieved from source file: {$this->_sourceFile}");
                }
            } else {
                $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not locate source file: {$this->_sourceFile}");
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "No source file specified.");
        }
        return $content;
    }

    /**
     * Retrieve the resource content stored in a physical file.
     *
     * @access public
     * @param string $file A path to the file representing the resource content.
     * @return string The content of the file, of false if it could not be
     * retrieved.
     */
    public function getFileContent($file, array $options = array()) {
        $content= false;
        $memory_limit= ini_get('memory_limit');
        if (!$memory_limit) $memory_limit= '8M';
        $byte_limit= $this->_bytes($memory_limit) * .5;
        $filesize= $this->getSourceFileSize($options);
        if ($this->getOne('ContentType')) {
            $type= $this->ContentType->get('mime_type') ? $this->ContentType->get('mime_type') : 'text/html';
            if ($this->ContentType->get('binary') || $filesize > $byte_limit) {
                if ($alias= array_search($this->xpdo->resourceIdentifier, $this->xpdo->aliasMap)) {
                    $name= basename($alias);
                } elseif ($this->get('alias')) {
                    $name= $this->get('alias');
                    if ($ext= $this->ContentType->getExtension()) {
                        $name .= ".{$ext}";
                    }
                } elseif ($name= $this->get('pagetitle')) {
                    $name= $this->cleanAlias($name);
                    if ($ext= $this->ContentType->getExtension()) {
                        $name .= ".{$ext}";
                    }
                } else {
                    $name= 'download';
                    if ($ext= $this->ContentType->getExtension()) {
                        $name .= ".{$ext}";
                    }
                }
                $header= 'Content-Type: ' . $type;
                if (!$this->ContentType->get('binary')) {
                    $charset= $this->xpdo->getOption('modx_charset',null,'UTF-8');
                    $header .= '; charset=' . $charset;
                }
                header($header);
                if ($this->ContentType->get('binary')) {
                    header('Content-Transfer-Encoding: binary');
                }
                if ($filesize > 0) {
                    $header= 'Content-Length: ' . $filesize;
                    header($header);
                }
                $header= 'Cache-Control: public';
                header($header);
                $header= 'Content-Disposition: ' . ($this->get('content_dispo') ? 'inline' : 'attachment' . '; filename=' . $name);
                header($header);
                $header= 'Vary: User-Agent';
                header($header);
                if ($customHeaders= $this->ContentType->get('headers')) {
                    foreach ($customHeaders as $headerKey => $headerString) {
                        header($headerString);
                    }
                }
                @session_write_close();
                while (@ob_end_clean()) {}
                readfile($file);
                die();
            }
            else {
                $content = file_get_contents($file);
            }
            if (!is_string($content)) {
                $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "modStaticResource->getFileContent({$file}): Could not get content from file.");
            }
        }
        return $content;
    }

    /**
     * Converts to bytes from PHP ini_get() format.
     *
     * PHP ini modifiers for byte values:
     * <ul>
     *  <li>G = gigabytes</li>
     *  <li>M = megabytes</li>
     *  <li>K = kilobytes</li>
     * </ul>
     *
     * @access protected
     * @param string $value Number of bytes represented in PHP ini value format.
     * @return integer The value converted to bytes.
     */
    protected function _bytes($value) {
        $value = trim($value);
        $modifier = strtolower($value{strlen($value)-1});
        switch($modifier) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        return $value;
    }
}
