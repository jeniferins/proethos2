<?php

namespace Proethos2\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


class SecurityController extends Controller
{
    /**
     * @Route("/login", name="login_route")
     * @Template()
     */
    public function loginAction()
    {
        $authenticationUtils = $this->get('security.authentication_utils');

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return array(
                // last username entered by the user
                'last_username' => $lastUsername,
                'error'         => $error,
            );
    }

    /**
     * @Route("/login_check", name="login_check")
     */
    public function loginCheckAction()
    {
        // this controller will not be executed,
        // as the route is handled by the Security system
    }

    /**
     * @Route("/logout", name="logout_route")
     */
    public function logoutAction()
    {
        // this controller will not be executed,
        // as the route is handled by the Security system
    }

    /**
     * @Route("/logged", name="default_security_target")
     */
    public function loggedAction()
    {
        // if secretary, send to committee home
        $user = $this->get('security.token_storage')->getToken()->getUser();

        if(in_array('secretary', $user->getRolesSlug())) {
            return $this->redirectToRoute('crud_committee_protocol_list', array(), 301);
        }
    }

    /**
     * @Route("/account/change_password", name="security_change_password")
     * @Template()
     */
    public function changePasswordAction()
    {
        $output = array();
        $request = $this->getRequest();
        $session = $request->getSession();
        $translator = $this->get('translator');
        $em = $this->getDoctrine()->getManager();

        $user = $this->get('security.token_storage')->getToken()->getUser();

        // checking if was a post request
        if($this->getRequest()->isMethod('POST')) {

            // getting post data
            $post_data = $request->request->all();
            
            // checking required fields
            foreach(array('change-password', 'password-confirm') as $field) {   
                if(!isset($post_data[$field]) or empty($post_data[$field])) {
                    $session->getFlashBag()->add('error', $translator->trans("Field '$field' is required."));
                    return $this->redirectToRoute('home', array(), 301);
                }
            }

            if($post_data['change-password'] != $post_data['password-confirm']) {
                $session->getFlashBag()->add('error', $translator->trans("Passwords doesn't match."));
                return $this->redirectToRoute('home', array(), 301);
            }

            $encoderFactory = $this->get('security.encoder_factory');
            $encoder = $encoderFactory->getEncoder($user);
            $salt = $user->getSalt(); // this should be different for every user
            $password = $encoder->encodePassword($post_data['change-password'], $salt);
            $user->setPassword($password);

            if($user->getFirstAccess()) {
                $user->setFirstAccess(false);
            }

            $em->persist($user);
            $em->flush();

            $session->getFlashBag()->add('success', $translator->trans("Password changed with success."));
            return $this->redirectToRoute('home', array(), 301);

        }

        return $output;
    }

    /**
     * @Route("/public/account/forgot_my_password", name="security_forgot_my_password")
     * @Template()
     */
    public function forgotMyPasswordAction()
    {
        $output = array();
        $request = $this->getRequest();
        $session = $request->getSession();
        $translator = $this->get('translator');
        $em = $this->getDoctrine()->getManager();

        // getting post data
        $post_data = $request->request->all();

        $user_repository = $em->getRepository('Proethos2ModelBundle:User');
        
        if(!isset($_REQUEST['hashcode'])) {
            throw $this->createNotFoundException($translator->trans('Invalid hashcode'));
        }

        $hashcode = $_REQUEST['hashcode'];
        $user = $user_repository->findOneByHashcode($hashcode);

        if(!$user) {
            throw $this->createNotFoundException($translator->trans('No user found'));
        }

        // checking if was a post request
        if($this->getRequest()->isMethod('POST')) {

            // getting post data
            $post_data = $request->request->all();
            
            // checking required fields
            foreach(array('new-password', 'confirm-password') as $field) {   
                if(!isset($post_data[$field]) or empty($post_data[$field])) {
                    $session->getFlashBag()->add('error', $translator->trans("Field '$field' is required."));
                    return $this->redirectToRoute('home', array(), 301);
                }
            }

            if($post_data['new-password'] != $post_data['confirm-password']) {
                $session->getFlashBag()->add('error', $translator->trans("Passwords doesn't match."));
                return $this->redirectToRoute('home', array(), 301);
            }

            $encoderFactory = $this->get('security.encoder_factory');
            $encoder = $encoderFactory->getEncoder($user);
            $salt = $user->getSalt(); // this should be different for every user
            $password = $encoder->encodePassword($post_data['new-password'], $salt);
            $user->setPassword($password);

            $user->cleanHashcode();

            $em->persist($user);
            $em->flush();

            $session->getFlashBag()->add('success', $translator->trans("Password changed with success."));
            return $this->redirectToRoute('home', array(), 301);

        }

        return $output;
    }
}