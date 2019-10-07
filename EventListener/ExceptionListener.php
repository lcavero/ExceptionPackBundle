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
    private $parameterBag;
    private $translator;
    private $enabled;
    private $from;
    private $to;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        \Swift_Mailer $mailer,
        TranslatorInterface $translator,
        $enabled,
        $from = "",
        $to = ""
    )
    {
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->enabled = $enabled;
        $this->from = $from;
        $this->to = $to;

        if($tokenStorage->getToken() != null){
            $user = $tokenStorage->getToken()->getUser();
            if($user instanceof UserInterface){
                $user = $user->getUsername();
            }
            $this->user = $user;
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $admin_email = $this->parameterBag->get('admin_email');

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
                    $message = "http_route_not_found";
                    $params = ['path' => $event->getRequest()->getRequestUri()];
                    break;
                }

                case MethodNotAllowedHttpException::class: {
                    $message = "http_method_not_allowed";
                    $params = ['method' => $event->getRequest()->getMethod()];
                    break;
                }

                default: {
                    $message = $exception->getMessage();
                }
            }
        }else{
            $status = 500;
            $message = 'critical_error';
            $params = ['admin_email' => $admin_email];
        }

        $data = [
            'code' => $status,
            'message' => $this->translator->trans($message, $params, 'exception', $locale)
        ];

        if($exception instanceof InvalidFormularyException){
            $data['constraints'] = $exception->getConstraintsErrors();
        }

        if($this->parameterBag->get('app_env') == "dev"){
            $data['exception'] = $exception->getMessage();
            $data['class'] = get_class($exception);
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
        }

        $response = new JsonResponse($data, $status, ['Content-Type' => 'application/json']);

        $event->setResponse($response);


        if($this->enabled && (!($exception instanceof HttpException) || $exception->getStatusCode() == 500)){
            $message = new \Swift_Message('Exception Critical');
            $message
                ->setFrom($this->from)
                ->setTo($this->to)
                ->setBody(
                    "<h1><b>Exception:</b> " . get_class($exception) . "</h1><p><b>User:</b> </p>"
                    . $this->user . "<p><b>Message:</b> </p>" . $exception->getMessage()
                    . "<p><b>File: </b></p>" . $exception->getFile() . "<p><b>Line: </b></p>"
                    . $exception->getLine() . "<p><b>Trace: </b></p>" . $exception->getTraceAsString()
                    , 'text/html')
            ;

            $this->mailer->send($message);
        }

    }
}
