<?php

namespace Peazie\GettyBrowserBundle\Controller;

use Guzzle\Http\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/getty")
 * @Template()
 */
class DefaultController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $images  = null;
        $session = $this->getRequest()->getSession();
        $cache   = $this->get('memcached');

        $form = $this->createFormBuilder()
            ->add('query', 'text')
            ->add('Search', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $query = $data['query'];

            $searchImagesArray = array (
                "RequestHeader" => array (
                    "Token" => $session->get('getty_token') // Token received from a CreateSession/RenewSession API call
                ),
                "SearchForImagesRequestBody" => array (
                    "Query" => array (
                        "SearchPhrase" => $query
                    ),
                    "ResultOptions" => array (
                        "IncludeKeywords" => "false",
                        "ItemCount" => 25, // return 25 items
                        "ItemStartNumber" => 1 // 1-based int, start at the first page
                    )
                )
            );

            $payload = json_encode( $searchImagesArray );

            if( !$images = $cache->get( 'query.' . $query ) ) {
                $client   = new Client();
                $request  = $client->post('http://connect.gettyimages.com/v2/search/SearchForImages', array(), $payload);
                $response = $request->send();

                $data = $response->json();
                if($data['SearchForImagesResult']['Images'] ) {
                    $results = $data['SearchForImagesResult']['Images'];

                    $size  = count($results);
                    $index = 0;

                    for($row=0; $row < $size; $row++ ) {
                        for( $col=0; $col<3; $col++ ) {
                            if( isset($results[$index]) ) {
                                $images[$row][$col] = $results[$index];
                                $index++;
                            }
                        }
                    }
		    $cache->set( 'query.' . $query, $images, 12*60*60 );
                } else {
		    print 'Error: <br />';
                    print_r($data); 
                    die;
                }
            }
        }

        return array(
            'form'   => $form->createView(),
            'images' => $images
        );
    }

    /**
     * @Route("/cc")
     * @Template()
     */
    public function clearAction()
    {
        $session = $this->getRequest()->getSession();
        $session->invalidate();

        print 'Session cleared!';
        die;
    }
}
