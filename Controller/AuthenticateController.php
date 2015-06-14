<?php

namespace Mayeco\AdwordsBundle\Controller;

use Mayeco\BaseBundle\Controller\Controller as BaseController;

use FOS\RestBundle\Controller\Annotations\QueryParam;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class AuthenticateController extends BaseController
{

    /**
     * @Route("/", name="index")
     */
    public function indexAction()
    {
        $refresh_token = $this->getsession("refresh_token");
        $google_id = $this->getsession("google_id");

        if($refresh_token && $google_id){
            return $this->redirectToRoute("app_home");
        }

        return $this->CreateView("MayecoAdwordsBundle::Inicio/home.html.twig");
    }

    /**
     * @Route("/redirect_authenticate", name="redirect_authenticate")
     * @QueryParam(name="state")
     * @QueryParam(name="hint")
     */
    public function redirectauthenticateAction($state, $hint)
    {
        $googleutils = $this->get("google.utils");

        if ($state) {
            $googleutils->getGoogleClient()->setState($state);
        }

        if ($hint) {
            $googleutils->getGoogleClient()->setLoginHint($hint);
        }

        $url = $googleutils->createAuthUrl();
        return $this->redirect($url);
    }

    /**
     * @Route("/authenticate", name="authenticate")
     * @QueryParam(name="code")
     * @QueryParam(name="error")
     * @QueryParam(name="state")
     */
    public function authenticateAction($code, $error, $state)
    {

        $this->session()->remove("google_id");

        if ($error) {

            if ("access_denied" == $error) {
                // user cancel
                $this->addFlash("notice", "access_denied");
                return $this->redirectToRoute("index");
            }

            // error
            $this->addFlash("notice", "generic error");
            return $this->redirectToRoute("index");
        }

        if (!$code) {
            // no Google valid code
            $this->addFlash("notice", "no valid code error");
            return $this->redirectToRoute("index");
        }

        $googleutils = $this->get("google.utils");

        $response = $googleutils->authenticateAccess($code);
        if (!$response) {
            // not posible to authenticate
            $this->addFlash("notice", "authenticate access error");
            return $this->redirectToRoute("index");
        }

        //now we are authenticate with Google store data in session
        $this->setsession("email", $response["email"]);
        $this->setsession("refresh_token", $response["refresh_token"]);
        $this->setsession("google_id", $response["userId"]);

        $this->addFlash("success", "authenticate OK!");
        return $this->redirectToRoute("app_home");
    }

}
