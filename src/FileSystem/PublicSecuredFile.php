<?php

/**
 * Jin Framework
 * Diatem
 */

namespace Jin2\FileSystem;

use Jin2\Assets\AssetsInterface;

/**
 * Permet la gestion de fichiers publics sécurisés. Les fichiers sont stockés sous
 * un nom hashé associé à un fichier clé. L'appel du fichier en direct, sans le
 * passage de la clé de vérification est impossible.
 * Cette classe est destiné au stockage de fichiers à vocation de disponibilité
 * limitée aux ayant-droits ayant eu accès à l'url sécurisée.
 * Un appel préalable à PublicSecuredFile::init() est requis afin de définir
 * dans quel dossier seront stockés les fichiers.
 * Lors de son premier appel la classe génère automatiquement les fichiers
 * nécessaires à son fonctionnement (.htaccess / read.php).  Pour modifier ces
 * fichiers, il faut surcharger les fonctions fournies par AssetsInterface.
 *
 * @example
 * PublicSecuredFile::init(__DIR__ . '/public/files/');
 * PublicSecuredFile::add($data);
 */
class PublicSecuredFile implements AssetsInterface
{

  /**
   * @var string  Dossier de stockage et d'accès des fichiers
   */
  protected static $storePath = null;

  /**
   * @var string  Dossier /vendor. Utilisé uniquement dans le cas de structures de dossiers non standart.
   */
  protected static $autoloaderPath = null;

  /**
   * @var integer  Longueur de la clé de sécurité générée
   */
  protected static $baseKeyLength = 16;

  /**
   * @var string  mode de calcul des clés de hashage (md4 par défaut)
   */
  protected static $hashMethod = 'md5';

  /**
   * @var string  Méthode de cryptage des données (aes128 par défaut)
   */
  protected static $encodeMethod = 'aes128';

  /**
   * @var string  Vecteur d'initialisation pour le cryptage des données
   */
  protected static $initializationVector = '1234567812345678';

  /**
   * @var string  Clé privée
   */
  protected static $privateKey = '67141ABCE7159153';

  /**
   * @var string  Nom du paramètres d'url
   */
  protected static $urlKeyArg = 'k';

  /**
  * Dernière erreur rencontrée

  * @var string
  */
  protected $lastError = '';

  /**
   * Initialisée avec succès
   *
   * @var boolean
   */
  protected $initialized = false;

  /**
   * Chemin d'accès
   *
   * @var string
   */
  protected $path;

  /**
   * Nom de la clé du fichier
   *
   * @var string
   */
  protected $accessKey;

  /**
   * Défini le dossier de stockage
   *
   * @param string $path
   */
  public static function init($storePath, $autoloaderPath = null)
  {
    self::$storePath  = $storePath;

    if ($autoloaderPath === null) {
      $autoloaderPath = preg_replace('#^(.*\\'. DIRECTORY_SEPARATOR .'vendor\\'. DIRECTORY_SEPARATOR .').*$#', '$1', realpath(__DIR__)) . 'autoload.php';
    }
    if (!file_exists($autoloaderPath)) {
      throw new \Exception('Autoloader introuvable. Veuillez renseigner le chemin du fichier autoload.php en second paramètre lors de l\'appel à PublicSecuredFile::init()');
      return null;
    }

    self::$autoloaderPath = $autoloaderPath;
  }

  /**
   * Retourne le chemin du dossier de stockage
   *
   * @return string
   * @throws \Exception
   */
  public static function getStorePath()
  {
    if (self::$storePath === null) {
      throw new \Exception('Vous devez définir un dossier de stockage avant toute utilisation avec PublicSecuredFile::init().');
      return null;
    }
    return self::$storePath;
  }

  /**
   * Constructeur
   *
   * @param string $path      Chemin relatif. (à partir du dossier image)
   * @param string $secureKey Clé d'accès
   */
  public function __construct($path, $secureKey)
  {
    $this->path = $path;

    $hashKey = hash(self::$hashMethod, $path . $secureKey);
    $this->accessKey = $hashKey;

    if (file_exists(static::getStorePath() . $hashKey) &&
      file_exists(static::getStorePath() . $hashKey . '.key')) {

      $f = new File(static::getStorePath() . $hashKey . '.key');
      if (self::decodeValue($f->getContent()) == $_REQUEST[static::getUrlKeyArg()]) {
        $this->initialized = true;
      } else {
        $this->lastError = 'Paramètre de sécurité incorrect';
      }
    } else {
      $this->lastError = 'Fichier indisponible';
    }
  }

  /**
   * Implements getAssetUrl function
   *
   * @param string $key
   * @return string
   */
  public static function getAssetUrl($key)
  {
    $root =  __DIR__
      . DIRECTORY_SEPARATOR .'..'
      . DIRECTORY_SEPARATOR .'..'
      . DIRECTORY_SEPARATOR .'assets/';
    switch ($key) {
      case 'htaccess':
        return $root . 'templates/htaccess.tpl';
      case 'read':
        return $root . 'templates/read.php.tpl';
    }
    return null;
  }

  /**
   * Implements getAssetContent function
   *
   * @param string $key
   * @return string
   */
  public static function getAssetContent($key)
  {
    if ($url = static::getAssetUrl($key)) {
      return file_get_contents($url, FILE_USE_INCLUDE_PATH);
    }
    return null;
  }

  /**
   * Retourne le nom du paramètre utilisé dans l'Url pour transmettre la clé
   *
   * @return string
   */
  public static function getUrlKeyArg()
  {
    return self::$urlKeyArg;
  }


  /**
   * Modifie le nombre de caractères des clés de sécurité
   *
   * @param integer $length
   */
  public static function setBaseKeyLength($length)
  {
    self::$baseKeyLength = $length;
  }


  /**
   * Ajoute une ressource sécurisée
   *
   * @param  string $fileToCopy    Chemin absolu ou relatif du fichier à copier
   * @param  string $relativePath  Chemin relatif souhaité à l'intérieur du dossier publicsecuredfiles/ ('' par défaut)
   * @return array('read' => 'lien pour lecture', 'download' => 'lien pour téléchargement') Les liens fournis n'incluent pas l'arborescence inférieure à publicsecuredfiles/
   * @throws \Exception
   */
  public static function add($fileToCopy, $relativePath = '')
  {
    static::checkSecureFolder();

    //Nom du fichier
    $parts = explode(DIRECTORY_SEPARATOR, $fileToCopy);
    $fileName = $parts[count($parts) - 1];

    //Modifier la fin du nom du dossier
    if (!empty($relativePath) && substr($relativePath, strlen($relativePath) - 1, 1) != DIRECTORY_SEPARATOR) {
      $relativePath .= DIRECTORY_SEPARATOR;
    }

    //Clé de sécurité
    $secureKey = self::generateRandomKey();

    //Nom du fichier sécurisé
    $hashKey = hash(self::$hashMethod, $relativePath . $fileName . $secureKey);

    //Copie du fichier sécurisé
    $r = copy($fileToCopy, static::getStorePath() . $hashKey);
    if (!$r) {
      throw new \Exception('Impossible de copier le fichier ' . $fileToCopy);
    }

    //Création du fichier clé
    $fileVerifyContent = static::encodeValue($secureKey);
    $verifyFile = new File(static::getStorePath() . $hashKey . '.key', true);
    $verifyFile->write($fileVerifyContent);

    $finalPath = $relativePath . $fileName . '?' . self::$urlKeyArg . '=' . $secureKey;
    return array('read' => $finalPath, 'download' => $finalPath.'&d=1');
  }

  /**
   * Supprime une ressource à partir de son url
   *
   * @param  string $url   Url sécurisée
   * @throws \Exception
   */
  public static function deleteFromUrl($url)
  {
    $url = str_replace('&d=1', '', $url);
    $parts = explode('?k=', $url);

    if(count($parts) != 2){
      throw new \Exception('Url non valide');
    }
    $file = $parts[0];
    $cle = $parts[1];

    //Nom du fichier sécurisé
    $hashKey = hash(self::$hashMethod, $file . $cle);
    if(!is_file(static::getStorePath().$hashKey) || !is_file(static::getStorePath().$hashKey.'.key')){
      throw new \Exception('Ressource inexistante');
    }

    unlink(static::getStorePath().$hashKey);
    unlink(static::getStorePath().$hashKey.'.key');
  }

  /**
   * Génère une clé aléatoire unique
   *
   * @return string
   */
  protected static function generateRandomKey()
  {
    $chars = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
    $baseKey = '';
    for ($i = 0; $i < self::$baseKeyLength; $i++) {
      $baseKey .= $chars[rand(0, count($chars) - 1)];
    }
    $baseKey .= uniqid();

    return $baseKey;
  }

  /**
   * Encode une valeur
   *
   * @param  string $valueToEncode Chaîne à encoder
   * @return string
   */
  protected static function encodeValue($valueToEncode)
  {
    return openssl_encrypt($valueToEncode, self::$encodeMethod, self::$privateKey, false, self::$initializationVector);
  }

  /**
   * Décode une valeur préalablement encodée
   *
   * @param  string $valueToDecode Chaîne à décoder
   * @return string
   */
  protected static function decodeValue($valueToDecode)
  {
    return openssl_decrypt($valueToDecode, self::$encodeMethod, self::$privateKey, false, self::$initializationVector);
  }

  /**
   * Vérifie que le dossier sécurisé soit présent et que les fichiers .htaccess et read.php soient présents
   *
   * @throws \Exception
   */
  protected static function checkSecureFolder()
  {
    if (!is_dir(static::getStorePath())) {
      mkdir(static::getStorePath());
    }
    if (!file_exists(static::getStorePath() . '.htaccess')) {
      $afc = static::getAssetContent('htaccess');
      $f = new File(static::getStorePath() . '.htaccess', true);
      $f->write($afc);
    }
    if (!file_exists(static::getStorePath() . 'read.php')) {
      $afc = static::getAssetContent('read');
      $afc = str_replace('%autoloader%', self::$autoloaderPath, $afc);
      $f = new File(static::getStorePath() . 'read.php', true);
      $f->write($afc);
    }
  }

  /**
   * Retourne la dernière erreur rencontrée (en verbose)
   *
   * @return string
   */
  public function getLastError()
  {
    return $this->lastError;
  }

  /**
   * Retourne si il s'agit d'une ressource valide. (Initialisée avec succès)
   *
   * @return boolean
   */
  public function isValid()
  {
    return $this->initialized;
  }

  /**
   * Effectue le rendu de la ressource directement dans la sortie navigateur
   *
   * @throws \Exception
   */
  public function renderInOutput()
  {
    if ($this->initialized) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $infos = finfo_file($finfo, static::getStorePath().$this->accessKey);

      header('Content-Type: '.$infos);
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . filesize(static::getStorePath().$this->accessKey));
      readfile(static::getStorePath().$this->accessKey);
    } else {
      throw new \Exception('Connexion securisée au fichier non vérifiée.');
    }
  }

  /**
   * Force le téléchargement du fichier
   *
   * @throws \Exception
   */
  public function forceDownload()
  {
    if ($this->initialized) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $infos = finfo_file($finfo, static::getStorePath().$this->accessKey);

      header('Content-Description: File Transfer');
      header('Content-Type: '.$infos);
      header('Content-Disposition: attachment; filename='.$this->path);
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . filesize(static::getStorePath().$this->accessKey));
      readfile(static::getStorePath().$this->accessKey);
    } else {
      throw new \Exception('Connexion securisée au fichier non vérifiée.');
    }
  }

  /**
   * Supprime la ressource iconographique sécurisée
   */
  public function delete()
  {
    unlink(static::getStorePath() . $this->accessKey);
    unlink(static::getStorePath() . $this->accessKey . '.key');
  }

}
