<?php 
namespace Peazie\GettyBrowserBundle\EventListener;

use Guzzle\Http\Client;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class GettyTokenListener
{
    private $getty_conf;

    public function __construct($getty_conf)
    {
        $this->getty_conf = $getty_conf;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $date    = new \DateTime();
        $now     = $date->getTimestamp() + 250;
        $request = $event->getRequest();
        $session = $event->getRequest()->getSession();

        //if it's explicit session clearing, skip everything
        if( strstr($request->getPathInfo(), 'cc') ) {
            return;
        }

        if( !$session->has('getty_token') OR ($now > $session->get('getty_expiry')) ) {

            $token_data = array(
                'RequestHeader' => array(
                    'Token'          => null,
                    'CoordinationId' => null
                ),
                'CreateSessionRequestBody' => array(
                    'SystemId'       => $this->getty_conf['system_id'],
                    'SystemPassword' => $this->getty_conf['system_password'],
                    'UserName'       => $this->getty_conf['username'],
                    'UserPassword'   => $this->getty_conf['user_password'],
                )
            );

            $client   = new Client();
            $request  = $client->post('https://connect.gettyimages.com/v1/session/CreateSession', array(), json_encode($token_data) );
            $response = $request->send();

            $data = $response->json();

            $session->set('getty_token',        $data['CreateSessionResult']['Token']       );
            $session->set('getty_secure_token', $data['CreateSessionResult']['SecureToken'] );
            $session->set('getty_expiry',       $date->getTimestamp() + ($data['CreateSessionResult']['TokenDurationMinutes']*60) );

        } 

        //may as we just ask for a new token - refer to above
        //else {
        //    $now = $date->getTimestamp() + 250;
        //    if( $now > $session->get('getty_expiry') ) {
        //        $renewData = array(
        //            'RequestHeader' => array(
        //                'Token' => $session->get('getty_token'),
        //                'CoordinationId' => null
        //            ),
        //            'RenewSessionRequestBody' => array(
        //                'SystemId' => 10667,
        //                'SystemPassword' => 'qROnPtNzhRakhg1a9MgrWpqsDgLbMgaCIE6+cDEdA3o='
        //            )
        //        );
        //        $client   = new Client();
        //        $request  = $client->post('https://connect.gettyimages.com/v1/session/RenewSession', array(), json_encode($renewData));
        //        $response = $request->send();
        //    }
        //}//else

    }//onKernelRequest

}//GettyTokenListener
