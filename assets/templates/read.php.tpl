<?php

require '%autoloader%';

use Jin2\FileSystem\PublicSecuredFile;

PublicSecuredFile::init('%storepath%');

if (!isset($_REQUEST['path']) || !isset($_REQUEST['k'])) {
  echo '404 - Paramètres manquants';
  header('HTTP/1.0 404 404 - Paramètres manquants');
  exit;
}

$psf = new PublicSecuredFile($_REQUEST['path'], $_REQUEST['k']);

if (!$psf->isValid()) {
  echo '404 - '.$psf->getLastError();
  header('HTTP/1.0 404 404 - '.$psf->getLastError());
  exit;
}

if (isset($_REQUEST['d'])) {
  $psf->forceDownload();
} else {
  $psf->renderInOutput();
}
