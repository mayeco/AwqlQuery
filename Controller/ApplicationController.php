<?php

namespace Mayeco\AdwordsBundle\Controller;

use Mayeco\BaseBundle\Controller\Controller as BaseController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * @Route("/app")
 */
class ApplicationController extends BaseController
{

    protected function clearSession()
    {
        // clear session data..
        $this->get('session')->remove("email");
        $this->get('session')->remove("refresh_token");
        $this->get('session')->remove("google_id");
    }

    protected function createQueryForm()
    {
        $defaultData = array('query' => 'your awql query');
        //create form
        return $this->createFormBuilder($defaultData)
            ->setAction($this->generateUrl('app_awqlquery'))
            ->add('query', 'textarea')
            ->add('send', 'submit')
            ->getForm();
    }

    /**
     * @Route("")
     * @Route("/", name="app_home")
     */
    public function indexAction()
    {

        $email = $this->getsession("email");
        $refresh_token = $this->getsession("refresh_token");
        $google_id = $this->getsession("google_id");

        if(!$refresh_token && !$google_id){
            $this->addFlash("error", "authenticate KO!");
            return $this->redirectToRoute("index");
        }

        $googleutils = $this->get("google.utils");
        if (!$tokeninfo = $googleutils->refreshAccess($google_id, $refresh_token)) {
            // clear session data..
            $this->clearSession();
            $this->addFlash("error", "authenticate KO!");
            return $this->redirectToRoute("index");
        }

        // get the current customer id...
        $form = $this->createQueryForm();
        $this->addData("email", $email);
        $this->addForm($form);

        return $this->CreateView("MayecoAdwordsBundle::App/home.html.twig");
    }

    /**
     * @Route("/data", name="app_awqlquery")
     * @Method({"POST"})
     */
    public function awqlqueryAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->CreateJsonView(array('result' => 'KO', 'message' => 'Only ajax!'));
        }

        $refresh_token = $this->getsession("refresh_token");
        $google_id = $this->getsession("google_id");

        if(!$refresh_token && !$google_id){
            return $this->CreateJsonView(array('result' => 'KO', 'message' => 'authenticate KO'));
        }

        $googleutils = $this->get("google.utils");
        if (!$tokeninfo = $googleutils->refreshAccess($google_id, $refresh_token)) {
            return $this->CreateJsonView(array('result' => 'KO', 'message' => 'authenticate KO'));
        }

        $form = $this->createQueryForm();
        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();
            $query = $data["query"];

            $customerservice = $googleutils->getAdwordsService("CustomerService");
            $customerdata = $customerservice->get();
            $googleutils->setAdwordsId($customerdata->customerId);

            $reporteraw = $googleutils->downloadReportWithAwql($query, "GZIPPED_XML");

            if($reporteraw) {

                $encoder = new XmlEncoder();
                $report = $encoder->decode($reporteraw, "xml");
                return $this->CreateJsonView(array('result' => 'OK', 'xml' => $report));

            } else {

                $exception = (string)$googleutils->getLastException();
                return $this->CreateJsonView(array('result' => 'KO', 'message' => "Error en la consulta", 'exception' => $exception));

            }
        }
    }

    /**
     * @Route("/exit", name="app_exit")
     */
    public function exitAction()
    {
        // clear session data..
        $this->clearSession();
        $this->addFlash("success", "exit ok!");
        return $this->redirectToRoute("index");

    }

}
