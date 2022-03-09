<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Faker\Factory;
use LogicException;

/*
 * @author Radosław Andraszyk <radoslaw.andraszyk@gmail.com>
 * @see https://api-platform.com/docs/core/controllers/
 */
final class imporetController extends AbstractController
{
    /**
     * @Route("/importer/fake-tours", methods={"GET"})
     * 
     * @return Response
     */
    public function __invoke(Request $request, ParameterBagInterface $params): Response
    {

        if($request->query->get('key') != $params->get('importer_api_key')){
            throw new LogicException('Access denied');
        }   

        $faker = Factory::create();
        $tours = array();

        for($i = 0; $i <= rand(1,15); $i++){

            $cities = array();
            $dates = array();
            $images = array();

            for($c = 0; $c <= rand(1,5); $c++){
                $cities[] = $faker->city();
                $dates[] = $faker->dateTimeBetween('now', '1 year', 'Europe/Warsaw');
                $images[] = "https://picsum.photos/1280/720.jpg";
            }

            $tours[] =array(
                'id' => $faker->uuid(),
                'name' => $faker->text(50),
                'description' => $faker->text(250),
                'destination' => [
                    'country' => $faker->country(),
                    'cities' => $cities
                ],
                'price' => number_format($faker->randomNumber(4), 2, ',', ' '),
                'language' => $faker->languageCode(),
                'duration' => $faker->randomNumber(1),
                'dates' => $dates,
                'company' => $faker->company(),
                'contact' => [
                    'adress' => $faker->address(),
                    'phone' => $faker->phoneNumber(),
                    'email' => $faker->email()
                ],
                'assets' => [
                    'images' => $images,
                    'pdf' => ['http://www.africau.edu/images/default/sample.pdf']
                ]
            );
        }

        return $this->json($tours);
    }
}