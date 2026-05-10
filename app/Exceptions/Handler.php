<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($this->shouldRedirectMissingPageToExplore($request, $exception)) {
            return redirect()->route('explore.index', [], 302);
        }

        return parent::render($request, $exception);
    }

    /**
     * Rutas web inexistentes o modelos no encontrados (p. ej. /p/{id}) → índice /explorar.
     * La API sigue respondiendo 404 JSON.
     */
    private function shouldRedirectMissingPageToExplore($request, Throwable $exception): bool
    {
        if ($request->is('api/*')) {
            return false;
        }

        return $exception instanceof ModelNotFoundException
            || $exception instanceof NotFoundHttpException;
    }
}
