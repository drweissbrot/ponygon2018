<template>
	<div class="join-lobby">
		<h2>
			Joining Lobby
			<span class="lobby-id">{{ $route.params.lobby }}</span>
		</h2>

		<p v-show="status == 'loading'">
			Loading...
		</p>

		<p v-show="status == 'lobbyDoesntExist'">
			This lobby doesn't exist.
			Do you want to
			<router-link :to="{ name: 'lobby.interstitial' }">create a lobby</router-link>?
		</p>

		<p v-show="status == 'error'">
			An error occured.
			Please try again later.
			Sorry about that :/
		</p>

		<div v-show="status == 'ready'" class="character-creation">
			<p>
				You'll join:
				<span v-for="player in namesInUse" class="player-list-item">{{ player }}</span>
			</p>

			<p>
				Please enter a username to join the lobby.
			</p>

			<!-- TODO Avatar Creation -->

			<label for="username">
				Username
			</label>

			<input type="text" id="name" placeholder="Enter your username..." v-model="name">

			<button @click="joinLobby">
				Join Lobby
			</button>
		</div>
	</div>
</template>

<script>
	const axios = require('axios')

	export default {
		data() {
			return {
				status: 'loading',
				namesInUse: [],
				name: ''
			}
		},

		mounted() {
			// TODO check with server if lobby exists
			axios.get('/lobby/heartbeat/' + this.$route.params.lobby)
			.then((res) => {
				this.status = 'ready'
				this.namesInUse = res.data.names_in_use
			})
			.catch((err) => {
				if (err.response.data.message = 'This lobby does not exist.') {
					return this.status = 'lobbyDoesntExist'
				}

				console.error(err)
				this.status = 'error'
			})
		},

		methods: {
			joinLobby() {
				if (this.namesInUse.includes(this.name)) {
					return alert('Your username is already in use!')
				}

				axios.post('/lobby/register', {
					name: this.name
				})
				.then((res) => {
					window.user = res.data
					window.Echo.options.auth.headers['X-PONYGON-USER'] = res.data.id
					window.Echo.options.auth.headers['X-PONYGON-AUTH'] = res.data.auth

					axios.post('/lobby/join/' + this.$route.params.lobby, {
						user: window.user.id,
						auth: window.user.auth
					})
					.then((res) => {
						this.$router.push({
							name: 'lobby',
							params: {
								lobby: this.$route.params.lobby
							}
						})
					})
					.catch((err) => {
						console.error(err)
					})
				})
				.catch((err) => {
					console.error(err)
				})
			}
		}
	}
</script>
