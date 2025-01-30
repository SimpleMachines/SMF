<?php

declare(strict_types=1);

namespace SMF\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SMF\Config;
use SMF\Db\DatabaseApi;
use SMF\Db\DatabaseApiInterface;
use SMF\Session;
use SMF\QueryString;

use function mt_rand;

class InitMiddleware implements MiddlewareInterface
{
	public function __construct(
		private Config $smfConfig,
		private DatabaseApi $db,
	) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
		$this->smfConfig::reloadModSettings();
		// Seed the random generator.
		if (empty(Config::$modSettings['rand_seed']) || mt_rand(1, 250) == 69) {
			// @TODO: Calls a deprecated function.
			Config::generateSeed();
		}
		$request = $request->withAttribute(Config::class, $this->smfConfig);
		$request = $request->withAttribute(DatabaseApiInterface::class, $this->db);

		Session::load();

        return $handler->handle($request);
    }
}
