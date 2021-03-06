<?php

namespace App\Games\Ponygon;

use Illuminate\Broadcasting\Broadcasters\RedisBroadcaster;
use Illuminate\Support\Str;

class PonygonRedisBroadcaster extends RedisBroadcaster
{
	/**
	 * Authenticate the incoming request for a given channel.
	 *
	 * @param \Illuminate\Http\Request $request
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
	 *
	 * @return mixed
	 */
	public function auth($request)
	{
		// This differenciates this Broadcaster from the normal RedisBroadcaster
		// we do not send back a 403 if the user is not signed in

		$channelName = Str::startsWith($request->channel_name, 'private-')
			? Str::replaceFirst('private-', '', $request->channel_name)
			: Str::replaceFirst('presence-', '', $request->channel_name);

		return $this->verifyUserCanAccessChannel(
			$request, $channelName
		);
	}

	/**
	 * Return the valid authentication response.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param mixed                    $result
	 *
	 * @return mixed
	 */
	public function validAuthenticationResponse($request, $result)
	{
		if (is_bool($result)) {
			return json_encode($result);
		}

		return json_encode([
			'channel_data' => [
				'user_info' => $result,
			],
		]);
	}
}
