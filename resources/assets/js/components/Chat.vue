<template>
	<div class="chat">
		<h3>Chat</h3>

		<div class="wrap">
			<div class="message-history" ref="history">
				<pg-chat-message v-for="message in messages"
					:key="message.id"
					:name="message.user"
					:message="message.message"
					:time="message.time"
					:isAction="message.isAction"
					:spacer="message.spacer">
				</pg-chat-message>
			</div>

			<form class="send-message" @submit.prevent="postChatMessage">
				<input type="text" placeholder="Type a message..." v-model="message">
			</form>
		</div>
	</div>
</template>

<script>
	const axios = require('axios')
	const moment = require('moment')

	export default {
		components: {
			'pg-chat-message': require('./Chat-Message.vue')
		},

		props: {
			lobby: {
				required: true
			},

			players: {
				required: true
			}
		},

		data() {
			return {
				messages: [],
				message: null
			}
		},

		mounted() {
			this.subscribe()
		},

		destroyed() {
			Echo.leave('lobby:' + this.lobby)
		},

		watch: {
			lobby(newLobby, oldLobby) {
				Echo.leave('lobby:' + oldLobby)

				this.subscribe()
			}
		},

		methods: {
			postChatMessage() {
				axios.post('/lobby/chat/' + this.lobby, {
					user: window.user.id,
					auth: window.user.auth,
					message: this.message
				})
				.then((res) => {
					this.message = null

					if (res.data.emitEventToParent) {
						this.$emit('chatMessageAnalyzed', res.data)
					}
				})
				.catch((err) => {
					let message =
						(err.response.data.message == 'You may not post chat messages after guessing the word.')
						? 'You may not post chat messages after guessing the word.'
						: 'Your message could not be posted'


					this.messages.push({
						user: null,
						message,
						time: [],
						isAction: true
					})

					this.message = null

					this.$nextTick(() => {
						this.$refs.history.scrollTop = this.$refs.history.scrollHeight
					})
				})
			},

			subscribe() {
				Echo.join('lobby:' + this.lobby)
				.joining((user) => {
					this.applyChatMessage({
						user: user.name,
						message: 'joined',
						isAction: true
					})
				})
				.leaving((user) => {
					this.applyChatMessage({
						user: user.name,
						message: 'left',
						isAction: true
					})
				})
				.listen('Game\\Lobby\\ChatMessage', this.applyChatMessage)
			},

			applyChatMessage(e) {
				let user = e.user ? this.findPlayerById(e.user) : null
				user = (user) ? user.name : e.user

				this.messages.push({
					id: this.messages.length,
					user,
					message: e.message,
					time: moment(e.time).format('MMM D, HH:mm:ss'),
					isAction: e.isAction
				})

				this.$nextTick(() => {
					this.$refs.history.scrollTop = this.$refs.history.scrollHeight
				})
			},

			applySpacer() {
				this.messages.push({
					spacer: true
				})

				this.$nextTick(() => {
					this.$refs.history.scrollTop = this.$refs.history.scrollHeight
				})
			},

			findPlayerById(id) {
				for (let player in this.players) {
					if (this.players[player].id === id) {
						return this.players[player]
					}
				}
			}
		}
	}
</script>
