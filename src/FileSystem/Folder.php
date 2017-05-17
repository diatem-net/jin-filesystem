<?php

/**
 * Jin Framework
 * Diatem
 */

namespace Jin2\FileSystem;

/**
 * Permet le parcours des fichiers d'un répertoire.
 * Initialiser l'objet, puis le parcourir avec un foreach.
 */
class Folder implements \Iterator
{

  /**
   * @var string Chemin relatif d'accès au dossier à parcourir
   */
  protected $folderPath;

  /**
   * @var array Tableau des fichiers/dossiers
   */
  protected $files = array();

  /**
   * @var string  Extensions à privilégier
   */
  protected $extensions = '';

  /**
   * Constructeur.  Initialiser l'objet, puis le parcourir avec un foreach($objet AS $fichier){ }
   *
   * @param  string  $folderPath         Chemin relatif du dossier que l'on souhaite parcourir
   * @param  string  $extensions         Extensions souhaitées, séparées par des virgules. (Par défaut liste tous les fichiers et dossiers)
   * @param  boolean $createIfNotExists  Le dossier est créé si il n'existe pas.
   * @throws \Exception
   */
  public function __construct($folderPath, $extensions = '', $createIfNotExists = false)
  {
    $this->folderPath = $folderPath;
    $this->extensions = $extensions;
    if (!is_dir($this->folderPath) && !$createIfNotExists) {
      throw new \Exception('Le dossier ' . $this->folderPath . ' n\'existe pas');
    } else if (!is_dir($this->folderPath) && $createIfNotExists) {
      mkdir($folderPath);
    }
    $this->buildData();
 }

  /**
  * Préconstruit les données à partir des données transmises au constructeur
  */
  protected function buildData()
  {
    $handle = opendir($this->folderPath);
    while (false !== ($file = readdir($handle))) {
      $vext = str_replace(',', '|', strtolower($this->extensions));
      if (!is_dir($file) && $file != '.' && $file != '..' && ($this->extensions == '' || preg_match("/\.(" . $vext . ")$/", strtolower($file)))) {
        $this->files[] = $file;
      }
    }
  }

  /**
   * Nombre d'éléments dans le dossier correspondant aux extensions souhaitées
   *
   * @return int
   */
  public function count()
  {
    return count($this->files);
  }

  /**
   * Fonction d'itération : CURRENT
   *
   * @return array
   */
  public function current()
  {
    return current($this->files);
  }

  /**
   * Fonction d'itération : KEY
   *
   * @return string
   */
  public function key()
  {
    return key($this->files);
  }

  /**
   * Fonction d'itération : REWIND
   *
   * @return \Jin2\filesystem\Folder
   */
  public function rewind()
  {
    reset($this->files);
    return $this;
  }

  /**
   * Fonction d'itération : NEXT
   */
  public function next()
  {
    next($this->files);
  }

  /**
   * Fonction d'itération : VALID
   *
   * @return boolean
   */
  public function valid()
  {
    return array_key_exists(key($this->files), $this->files);
  }

  /**
   * Supprime le contenu du dossier
   */
  public function deleteContent()
  {
    $files = glob($this->folderPath . '*');
    foreach ($files as $file) {
      if (is_file($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Supprime le dossier et son contenu
   */
  public function delete()
  {
    $this->deleteContent();
    unlink($this->folderPath);
  }

  /**
   * Retourne la partie commune des chemins de plusieurs dossiers
   *
   * @param  array $directories  Liste de dossiers
   * @return string              Partie commune des paths
   */
  public static function findCommonPath($directories)
  {
    while(count($directories) !== 1) {
      usort($directories, function($a, $b) {
        return strlen($b) - strlen($a);
      });
      $directories[0] = dirname($directories[0]);
      $directories = array_unique($directories);
    }
    return reset($directories);
  }

}
