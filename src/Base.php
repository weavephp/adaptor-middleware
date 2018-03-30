<?php
/**
 * Weave Middleware Adaptor.
 */
namespace Weave\Adaptor\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * The middleare adaptor.
 *
 * This base class lets you write middleware compatible with multiple standards.
 */
abstract class Base
{
	/**
	 * The handler for the next middleware in the chain.
	 *
	 * This value could be a callable or an object with either
	 * a 'handle' method or a 'process' method.
	 *
	 * @var mixed
	 */
	private $adaptorNext;

	/**
	 * The response object, if one was supplied.
	 *
	 * @var null|Response
	 */
	private $adaptorResponse = null;

	/**
	 * Invoke entrypoint.
	 *
	 * Used by various middleware stacks, both single and double-pass - but not the
	 * PSR15 standard which uses the process() method.
	 *
	 * The second parameter might be a PSR7-style response instance (for double-pass)
	 * or it might be some form of callable (for single-pass).
	 *
	 * @param Request  $request  The PSR7 request.
	 * @param mixed    $response Some form of PSR7-style response or a PSR15 delegate.
	 * @param callable $next     Some form of callable to the next pipeline entry.
	 *
	 * @return mixed Some form of PSR7-style Response.
	 */
	public function __invoke(Request $request, $response, $next = null)
	{
		// Cope with invoked single-pass and invoked double-pass middlewares
		$this->adaptorNext = is_callable($response) ? $response : $next;
		$this->adaptorResponse = is_callable($response) ? null : $response;
		return $this->run($request);
	}

	/**
	 * PSR15 single-pass entrypoint.
	 *
	 * PSR15 has been through a lot of redesign and while most of them
	 * agreed on the use of a 'process' method, the signature has changed
	 * so there is no type hinting here. The final PSR15 spec. also
	 * requires PHP7.1 because it uses a return type hint but I need this
	 * class to support php5.6 upwards.
	 *
	 * @param Request $request The PSR7 request.
	 * @param mixed   $next    Some form of delegate to the next pipeline entry.
	 *
	 * @return mixed Some form of PSR7-style Response.
	 */
	public function process(Request $request, $next)
	{
		$this->adaptorNext = $next;
		return $this->run($request);
	}

	/**
	 * Define this method in your middleware class to do the actual work.
	 *
	 * @param Request $request The PSR7 request object.
	 *
	 * @return mixed Some form of PSR7-style Response.
	 */
	abstract protected function run(Request $request);

	/**
	 * Call this from within your do() method to chain to the next middleware in the stack.
	 *
	 * @param Request $request The PSR7 request.
	 *
	 * @return mixed Some form of PSR7-style Response.
	 */
	protected function chain(Request $request)
	{
		if (is_object($this->adaptorNext)) {
			if (method_exists($this->adaptorNext, 'handle')) {
				return $this->adaptorNext->handle($request);
			}
			if (method_exists($this->adaptorNext, 'process')) {
				return $this->adaptorNext->process($request);
			}
		}
		if ($this->adaptorResponse !== null) {
			return ($this->adaptorNext)($request, $this->adaptorResponse);
		}
		return ($this->adaptorNext)($request);
	}

	/**
	 * Returns the response object or null.
	 *
	 * Rarely needed - usually if you think you need the response object you are doing it wrong!
	 *
	 * @return Response|null
	 */
	protected function getResponseObject()
	{
		return $this->adaptorResponse;
	}
}
