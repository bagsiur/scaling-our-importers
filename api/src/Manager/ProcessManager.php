<?php

namespace App\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\TourMessage;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use App\Entity\Tour;

/*
 * @author Radosław Andraszyk <radoslaw.andraszyk@gmail.com>
 */
class ProcessManager
{
    /**
     * @var string
     */
    protected $url = 'caddy';

    /**
     * @var string
     */
    protected $key = '70db5ccd-2661-4348-838f-a4039e75db6c';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FilesystemOperator
     */
    private $toursStorage;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var array
     */
    private $thumbs = [
        '_s' => ['MAX_WIDTH' => 90, 'MAX_HEIGHT' => 90],
        '_m' => ['MAX_WIDTH' => 240, 'MAX_HEIGHT' => 240],
        '_b' => ['MAX_WIDTH' => 480, 'MAX_HEIGHT' => 480]
    ];

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger, FilesystemOperator $toursStorage, FilesystemOperator $assetsStorage, MessageBusInterface $bus, EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->assetsStorage = $assetsStorage;
        $this->toursStorage = $toursStorage;
        $this->bus = $bus;
        $this->entityManager = $entityManager;
    }

    /**
     * Function for call API method
     *
     * @param string $action
     * @param string $method
     * 
     * @return array $json
     */
    public function callAPI(string $action, string $method)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url.$action.'?key='.$this->key,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => $method,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false
        ));
        
        $response = curl_exec($curl);

        $info = curl_getinfo($curl);
        curl_close($curl);
    
        return array(
            'code' => $info["http_code"],
            'data' => json_decode($response, true)
        );
        
    }

    /**
     * @return array
     */
    public function getTours(): ?array
    {
        $response = $this->callAPI('/importer/fake-tours', 'GET');
        return $response['code'] == 200 ? $response['data'] : null;
    }

    public function import(OutputInterface $output): bool
    {

        $tours = $this->getTours();
        $progressBar = new ProgressBar($output, count($tours));

        $this->logger->debug("Start importing tours");
    
        foreach($tours as $tour){

            $this->logger->info('Import of tour from API [id:'.$tour['id'].']', $tour);
            
            $transformedJSON = array(
                'time' => time(),
                'tourId' => $tour['id'],
                'tourName' => $tour['name'],
                'tourDescription' => $tour['description'],
                'tourPrice' => str_replace(array(' ', ','), array('', '.'), $tour['price']),
                'tourDuration' => $tour['duration'],
                'tourImages' => $tour['assets']['images'],
                'tourPdf' => $tour['assets']['pdf'],
                'tourImporterData' => $tour
            );

            $this->toursStorage->write($tour['id'], json_encode($transformedJSON));
            $this->triggerNewTourMessage($tour['id']);
            
            $progressBar->advance();
        }

        $this->logger->debug("End of tour import");
        $progressBar->finish();

        return true;
    }

    public function triggerNewTourMessage(string $id): void
    {
        $this->bus->dispatch(
            new TourMessage($id)
        );
    }

    public function proccessTourMessage(TourMessage $message): void
    {
        $tour = json_decode($this->toursStorage->read($message->getId()), true); 

        $images = $this->processFiles($message->getId(), $tour['tourImages']);
        $pdfs = $this->processFiles($message->getId(), $tour['tourPdf']);
        $this->prepareThumbnails($message->getId(), $images);

        $tourWebsiteDB = new Tour();

        $tourWebsiteDB->setName($tour['tourName']);
        $tourWebsiteDB->setDescription($tour['tourDescription']);
        $tourWebsiteDB->setPrice($tour['tourPrice']);
        $tourWebsiteDB->setDuration($tour['tourDuration']);
        $tourWebsiteDB->setImages($images);
        $tourWebsiteDB->setPdfs($pdfs);
        $tourWebsiteDB->setData($tour);

        $this->entityManager->persist($tourWebsiteDB);
        $this->entityManager->flush();
        $this->toursStorage->delete($message->getId());

    }

    public function processFiles(string $id, array $files): array
    {

        $processedFiles = [];
        $i = 1;

        foreach($files as $file){

            $name = basename($file);
            $ext = pathinfo($name, PATHINFO_EXTENSION);

            $fileName = time().'_'.$i.'.'.$ext;
            $content = file_get_contents($file);

            $this->assetsStorage->write(
                $id.'/'.$fileName,
                $content
            );

            $processedFiles[] = $fileName;
            $i++;
        }

        return $processedFiles;

    }

    public function prepareThumbnails(string $id, array $images): void
    {

        $imagine = new Imagine();

        foreach($images as $image){

            $filename = dirname(__DIR__).'/../assets/'.$id.'/'.$image;
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $thumb = str_replace('.'.$ext, '', $filename);

            list($iwidth, $iheight) = getimagesize($filename);
            $ratio = $iwidth / $iheight;

            foreach($this->thumbs as $postfix => $size){
                
                $width = $size['MAX_WIDTH'];
                $height = $size['MAX_HEIGHT'];

                if ($width / $height > $ratio) {
                    $width = $height * $ratio;
                } else {
                    $height = $width / $ratio;
                }
        
                $photo = $imagine->open($filename);
                $photo->resize(new Box($width, $height))->save($thumb.$postfix.'.'.$ext);

            }
        }
    }

}