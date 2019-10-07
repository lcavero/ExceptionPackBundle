<?php

namespace LCV\ExceptionPackBundle\EventListener;

use LCV\ExceptionPackBundle\Exception\InvalidFormularyException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExceptionListener
{
    private $user;
    private $mailer;
    private $translator;

    private $environment;
    private $contact_email;
    private $error_emails_config;


    public function __construct(
        TokenStorageInterface $tokenStorage,
        \Swift_Mailer $mailer,
        TranslatorInterface $translator,
        $environment,
        $contact_email,
        $error_emails_config
    )
    {
        $this->mailer = $mailer;
        $this->translator = $translator;

        if($tokenStorage->getToken() != null){
            $user = $tokenStorage->getToken()->getUser();
            if($user instanceof UserInterface){
                $user = $user->getUsername();
            }else if(!is_string($user)){
                $user = strval($user);
            }
        }else{
            $user = "Anonymous";
        }

        $this->user = $user;

        $this->environment = $environment;
        $this->contact_email = $contact_email;
        $this->error_emails_config = $error_emails_config;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $params = [];
        $locale = null;

        if($exception instanceof HttpException){
            $status = $exception->getStatusCode();

            if(($exception instanceof NotFoundHttpException) || ($exception instanceof MethodNotAllowedHttpException)){
                // Estas excepciones se producen antes de que se resuelva el locale, por lo que hay que establecerlo a mano
                $path = $event->getRequest()->getRequestUri();
                $pathParams = explode("/", $path);

                if(isset($pathParams[1])){
                    $locale = \Locale::canonicalize($pathParams[1]);
                }
            }

            switch (get_class($exception)){
                case NotFoundHttpException::class: {
                    $message = "lcv.http_route_not_found";
                    $params = ['path' => $event->getRequest()->getRequestUri()];
                    break;
                }

                case MethodNotAllowedHttpException::class: {
                    $message = "lcv.http_method_not_allowed";
                    $params = ['method' => $event->getRequest()->getMethod()];
                    break;
                }

                default: {
                    $message = $exception->getMessage();
                }
            }
        }else{
            $status = 500;
            $message = 'lcv.critical_error';
            $params = ['contact_email' => $this->contact_email];
        }

        $data = [
            'code' => $status,
            'message' => $this->translator->trans($message, $params, 'exceptions', $locale)
        ];

        if($exception instanceof InvalidFormularyException){
            $data['constraints'] = $exception->getConstraintsErrors();
        }

        if($this->environment == "dev"){
            $data['exception'] = $exception->getMessage();
            $data['class'] = get_class($exception);
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
        }

        $response = new JsonResponse($data, $status, ['Content-Type' => 'application/json']);

        $event->setResponse($response);

        if($this->error_emails_config['enabled'] && (!($exception instanceof HttpException) || $exception->getStatusCode() == 500)){
            $mailMessage = new \Swift_Message('Exception Critical');
            $mailMessage
                ->setFrom($this->error_emails_config['from_email'])
                ->setTo($this->error_emails_config['to_email'])
                ->setBody(
                    "<h1><b>Exception:</b> " . get_class($exception) . "</h1><p><b>User:</b> </p>"
                    . $this->user . "<p><b>Message:</b> </p>" . $exception->getMessage()
                    . "<p><b>File: </b></p>" . $exception->getFile() . "<p><b>Line: </b></p>"
                    . $exception->getLine() . "<p><b>Trace: </b></p>" . $exception->getTraceAsString()
                    , 'text/html')
            ;

            $this->mailer->send($mailMessage);
        }

    }
}
