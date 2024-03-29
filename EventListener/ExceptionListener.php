<?php

namespace LCV\ExceptionPackBundle\EventListener;

use LCV\CommonExceptions\Exception\ApiException;
use LCV\CommonExceptions\Exception\InvalidConstraintsException;
use Swift_Mailer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExceptionListener
{
    private $user;
    private $realUser;
    private $mailer;
    private $translator;

    private $environment;
    private $contact_email;
    private $error_emails_config;


    public function __construct(
        TokenStorageInterface $tokenStorage,
        Swift_Mailer $mailer,
        TranslatorInterface $translator,
        $environment,
        $contact_email,
        $error_emails_config
    )
    {
        $this->mailer = $mailer;
        $this->translator = $translator;

        if($tokenStorage->getToken() != null){
            $this->realUser = $user = $tokenStorage->getToken()->getUser();
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

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getException();

        $params = [];
        $locale = null;
        $status = null;
        $message = null;


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

            case AccessDeniedException::class: {

                if($this->realUser instanceof UserInterface){
                    $status = 403;
                    $message = "lcv.security_forbidden_access";
                }else{
                    $status = 401;
                    $message = "lcv.security_unauthorized_access";
                }
                break;
            }

            default: {
                $message = $exception->getMessage();
            }
        }


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
        }else{
            $status = $status ?: 500;
            $message = $message ?: 'lcv.critical_error';
            $params = empty($params) ? $params : ['contact_email' => $this->contact_email];
        }

        if($exception instanceof ApiException){
            $params = array_merge($params, $exception->getTranslationParams());
        }

        $data = [
            'code' => $status,
            'message' => $this->translator->trans($message, $params, 'exceptions', $locale)
        ];

        if($exception instanceof InvalidConstraintsException){
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
