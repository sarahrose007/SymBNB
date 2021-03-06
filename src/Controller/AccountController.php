<?php

namespace App\Controller;

use App\Entity\Ad;
use App\Entity\User;
use App\Form\AccountType;
use App\Entity\PasswordUpdate;
use App\Form\RegistrationType;
use App\Form\PasswordUpdateType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AccountController extends AbstractController
{
    /**
     * Login route
     * 
     * @Route("/login", name="account_login")
     * 
     * @return Response
     */
    public function login(AuthenticationUtils $utils)
    {
        $error = $utils->getLastAuthenticationError();
        $username = $utils->getLastUsername();

        return $this->render('account/login.html.twig', 
        [
            'hasError' => $error !== null,
            'username' => $username
        ]);
    }

    /**
     * Logout
     *
     * @Route("/logout", name="account_logout")
     * 
     * @return void
     */
    public function logout() 
    {
        // Nothing to do here
    }

    /**
     * Display registration form
     * 
     * @Route("/register", name="account_register")
     *
     * @return Response
     */
    public function register(Request $request, ObjectManager $manager, UserPasswordEncoderInterface $encoder) 
    {
        $user = new User();

        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $hash = $encoder->encodePassword($user, $user->getHash());
            $user->setHash($hash);

            $manager->persist($user);
            $manager->flush();

            $this->addFlash(
                'success',
                "You registered successfully !"
            );

            return $this->redirectToRoute("account_login");
        }

        return $this->render('account/registration.html.twig', 
        [
            'form' => $form->createView()
        ]);
    }

    /**
     * Display and handle profile modification form
     * 
     * @Route("/account/profile", name="account_profile")
     * 
     * @IsGranted("ROLE_USER")
     *
     * @return Response
     */
    public function profile(Request $request, ObjectManager $manager)
    {
        $user = $this->getUser(); #Get the current connected user

        $form = $this->createForm(AccountType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            //$manager->persist($user); entity already exists
            $manager->flush();

            $this->addFlash(
                'success',
                "Modifications have been successfully saved !"
            );

        }

        return $this->render('account/profile.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * Allow password modification
     * 
     * @Route("/account/password-update", name="account_password")
     * 
     * @IsGranted("ROLE_USER")
     *
     * @return Response
     */
    public function updatePassword(Request $request, ObjectManager $manager, UserPasswordEncoderInterface $encoder)
    {
        $passwordUpdate = new PasswordUpdate();
        $user = $this->getUser();

        $form = $this->createForm(PasswordUpdateType::class, $passwordUpdate);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            # Verify old password = current user's password
            if(!password_verify($passwordUpdate->getOldPassword(), $user->getHash())) {
                # Handle error
                $form->get('oldPassword')->addError(new FormError("Wrong password :s"));
            } else {
                $newPassword = $passwordUpdate->getNewPassword();

                $hash = $encoder->encodePassword($user, $newPassword);

                $user->setHash($hash);

                $manager->persist($user);
                $manager->flush();

                $this->addFlash(
                    'success',
                    "Password has been successfully changed !"
                );

                return $this->redirectToRoute("homepage");
            }
        }

        return $this->render('account/password.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * Show current's user account
     *
     * @Route("/account", name="account_index")
     * 
     * @IsGranted("ROLE_USER")
     * 
     * @return Response
     */
    public function myAccount() {
        return $this->render('user/index.html.twig', [
            'user' => $this->getUser()
        ]);
    }

    /**
     * Allow ad removal
     * 
     * @Route("/ads/{slug}/delete", name="ads_delete")
     * 
     * @Security("is_granted('ROLE_USER') and user == ad.getAuthor()", message="You can't access this page :x")
     *
     * @param Ad $ad
     * @param ObjectManager $manager
     * @return Response
     */
    public function delete(Ad $ad, ObjectManager $manager)
    {
        $manager->remove($ad);
        $manager->flush();

        $this->addFlash(
            'success',
            "Your ad \"{$ad->getTitle()}\" has been successfully deleted !"
        );

        return $this->redirectToRoute("ads_index");
    }

    /**
     * Display a user's bookings list
     * 
     * @Route("/account/bookings", name="account_bookings")
     *
     * @return Response
     */
    public function showBookings()
    {
        return $this->render('account/bookings.html.twig');
    }


}
