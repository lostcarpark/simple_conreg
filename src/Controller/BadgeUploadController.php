<?php

namespace Drupal\simple_conreg\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for ConReg - Simple Convention Registration routes.
 */
class BadgeUploadController extends ControllerBase {

  /**
   * The file handler.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The HTTP request.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function __construct(FileSystemInterface $file_system, Request $request) {
    $this->fileSystem = $file_system;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Function used for badge uploading.
   */
  public function badgeUpload($eid) {
    $pngdata = $this->request->request->get('data');
    if (!empty($pngdata)) {
      [$id, $base64] = explode('|', $pngdata);
      [, $data]      = explode(';', $base64);
      [, $data]      = explode(',', $data);
      $pngdata       = base64_decode($data);
      $path          = 'public://badges/' . $eid;
      $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
      FileRepositoryInterface::writeData($pngdata, $path . '/' . $id . '.png', FileSystemInterface::EXISTS_REPLACE);
    }

    $content['markup'] = [
      '#markup' => '<p>Badge Upload.</p>',
    ];
    return $content;
  }

}
