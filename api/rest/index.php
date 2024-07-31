<?php
require_once dirname(__FILE__) . '/../../helpers/constants.php';
require_once SITE_DOCUMENT_ROOT . 'helpers/restler.php';

use Luracast\Restler\Restler;

$rest = new Restler();
$rest->setSupportedFormats('XmlFormat', 'JsonFormat', 'JsFormat', 'JpgFormat', 'UploadFormat');
$rest->addAPIClass('LuraCast\\Restler\\Resources');
$rest->addAPIClass('Core','');
$rest->handle();
?>
