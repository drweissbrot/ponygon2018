<template>
	<div class="drawonary">
		<h2>{{ title }}</h2>

		<div class="board-wrap">
			<pg-player-list :players="players"></pg-player-list>

			<div></div>

			<pg-draw-board ref="board"
				:words="words"
				:players="players"
				:endGameScoreboard="endGameScoreboard"
				:rounds="rounds"
				:round="round"
				:wordLength="wordLength"
				:wordToGuess="wordToGuess"
				:turn="turn"
				:action="action"
				:turnEndsAt="turnEndsAt"
				:endsAtIsSelection="endsAtIsSelection"
				:turnEnded="turnEnded"
				:gameEnded="gameEnded"
				:selectingUser="selectingUser"
				:lobby="lobbyId"
				:drawing="drawing"
				@wordSelected="selectWord"
				@startDrawing="startDrawing"
				@continueDrawing="continueDrawing"
				@stopDrawing="stopDrawing"
				@canvasDimensions="canvasDimensions"
				@clearCanvas="clearCanvas">
			</pg-draw-board>

			<div></div>

			<pg-chat ref="chat"
				:lobby="this.lobbyId"
				:players="players"
				@chatMessageAnalyzed="closeGuess">
			</pg-chat>
		</div>
	</div>
</template>

<script>
	const axios = require('axios')
	const moment = require('moment')

	let drawingChannel

	export default {
		components: {
			'pg-draw-board': require('./DrawonaryBoard.vue')
		},

		data() {
			return {
				id: this.$route.params.id,
				lobbyId: null,

				words: null,
				wordToGuess: null,
				wordLength: 0,

				round: null,
				rounds: null,

				turn: null,
				drawing: false,

				action: null,
				turnEndsAt: null,
				endsAtIsSelection: false,

				selectingUser: false,

				turnEnded: false,
				gameEnded: false,

				players: [],
				order: [],
				endGameScoreboard: null,

				title: 'Donnerstagsmaler'
			}
		},

		async mounted() {
			this.updateTitle()

			await this.getStatus()
			this.subscribe()
		},

		methods: {
			updateTitle() {
				moment.locale('de')
				this.title = moment().format('dddd') + 'smaler'

				setTimeout(this.updateTitle, 120000)
			},

			getStatus() {
				return axios.post('/play/draw/status/' + this.id, {
					user: window.user.id,
					auth: window.user.auth
				})
				.then((res) => {
					this.id = res.data.id
					this.lobbyId = res.data.lobby_id
					this.deck = res.data.deck
					this.turn = res.data.turn
					this.drawing = (res.data.turn == window.user.id)
					this.round = res.data.round
					this.rounds = res.data.rounds

					this.order = res.data.order.split(':')

					this.applyScoreboardSorted(res.data.scoreboard)

					this.onSelectingWord({
						user: this.turn,
						selectionEndsAt: moment().add(14, 'seconds').format()
					})
				})
				.catch((err) => {
					console.error(err)
				})
			},

			subscribe() {
				Echo.channel('game:' + this.id)
				.listen('Game\\Drawonary\\SelectingWord', this.onSelectingWord)
				.listen('Game\\Drawonary\\WordSelected', this.onWordSelected)
				.listen('Game\\Drawonary\\TurnEnded', this.onTurnEnded)
				.listen('Game\\Drawonary\\WordGuessed', this.onWordGuessed)
				.listen('Game\\Drawonary\\RoundAdvanced', this.onRoundAdvanced)
				.listen('Game\\Drawonary\\GameEnded', this.onGameEnded)
				.listen('Game\\Drawonary\\ShowLetter', this.onShowLetter)

				drawingChannel = Echo.private('game:draw:' + this.id)
				.listenForWhisper('startDrawing', this.onRemoteStartDrawing)
				.listenForWhisper('continueDrawing', this.onRemoteContinueDrawing)
				.listenForWhisper('stopDrawing', this.onRemoteStopDrawing)
				.listenForWhisper('canvasDimensions', this.onRemoteCanvasDimensions)
				.listenForWhisper('clearCanvas', this.$refs.board.$refs.drawingboard.clearCanvas)
			},

			onSelectingWord(e) {
				this.$refs.board.$refs.drawingboard.clearCanvas()
				this.$refs.board.$refs.drawingboard.canvasDimensions({
					color: '#000',
					weight: 5,
				})

				this.wordToGuess = null
				this.wordLength = null

				if (e.user == window.user.id) {
					// current user is selecting word! PANIC!
					this.getWords()
				}

				this.turnEndsAt = e.selectionEndsAt
				this.endsAtIsSelection = true
				this.turn = e.user
				this.drawing = (e.user == window.user.id)
				this.action = 'selecting a word'
				this.turnEnded = false

				if (e.user != window.user.id) {
					this.selectingUser = this.turn
				}
			},

			onWordSelected(e) {
				this.action = 'drawing'
				this.turnEndsAt = e.turnEndsAt
				this.endsAtIsSelection = false
				this.words = null
				this.selectingUser = false

				if (this.turn == window.user.id && ! this.wordToGuess) {
					return this.getWordToGuess()
				}

				if (this.turn != window.user.id || ! this.wordToGuess) {
					this.wordLength = e.wordLength
				}
			},

			onTurnEnded(e) {
				if (this.gameEnded) return

				this.turnEnded = JSON.parse(e.addedPoints)

				this.applyScoreboardSorted(e.scoreboard)

				this.wordToGuess = e.word

				this.$refs.chat.applyChatMessage({
					message: 'The word was ' + e.word + '!',
					isAction: true
				})

				this.$refs.chat.applySpacer()
			},

			onWordGuessed(e) {
				this.$refs.chat.applyChatMessage({
					user: e.user,
					message: 'guessed the word!',
					isAction: true,
					time: []
				})

				// TODO not doing this after every guess prevents this
				// scoreboard from overwriting the scoreboard from
				// turnEnd -- find a nicer way to do this
				// this.applyScoreboardSorted(e.scoreboard)
			},

			onRoundAdvanced(e) {
				this.round = (e.round > this.rounds) ? this.rounds : e.round
			},

			onGameEnded(e) {
				this.gameEnded = true
				this.turnEnded = false

				this.endGameScoreboard = JSON.parse(e.scoreboard)
				this.applyScoreboardSorted(e.scoreboard)
			},

			onShowLetter(e) {
				this.$refs.board.showLetter(e.position, e.letter)
			},

			closeGuess(e) {
				this.$refs.chat.applyChatMessage({
					user: null,
					message: e.word + ' is close!',
					time: [],
					isAction: true
				})
			},

			getWordToGuess() {
				axios.post('/play/draw/get-word/' + this.id, {
					user: window.user.id,
					auth: window.user.auth
				})
				.then((res) => {
					this.wordToGuess = res.data.word
				})
				.catch((err) => {
					console.error(err)
				})
			},

			applyScoreboardSorted(scoreboard) {
				scoreboard = JSON.parse(scoreboard)

				let order = Object.assign({}, this.order)

				for (let i in order) {
					for (let j in scoreboard) {
						if (order[i] == scoreboard[j].id) {
							order[i] = scoreboard[j]
						}
					}

					// for (let j = 0; j < scoreboard.length; j++) {
					// 	order[i] = scoreboard[j]
					// }
				}

				// TODO is this order actually the right order?
				this.players = order
			},

			getWords() {
				axios.post('/play/draw/words/' + this.id, {
					user: window.user.id,
					auth: window.user.auth
				})
				.then((res) => {
					this.words = res.data.words
				})
				.catch((err) => {
					console.error(err)
				})
			},

			selectWord(word) {
				axios.post('/play/draw/select/' + this.id, {
					user: window.user.id,
					auth: window.user.auth,
					word
				})
				.then((res) => {
					this.wordToGuess = word
					this.words = null
				})
				.catch((err) => {
					console.error(err)
				})
			},

			findPlayerById(id) {
				for (let player in this.players) {
					if (this.players[player].id === id) {
						return this.players[player]
					}
				}
			},

			startDrawing(e) {
				drawingChannel.whisper('startDrawing', e)
			},

			continueDrawing(e) {
				drawingChannel.whisper('continueDrawing', e)
			},

			stopDrawing() {
				drawingChannel.whisper('stopDrawing')
			},

			canvasDimensions(e) {
				if (! drawingChannel) return

				drawingChannel.whisper('canvasDimensions', e)
			},

			clearCanvas() {
				drawingChannel.whisper('clearCanvas')
			},

			onRemoteStartDrawing(e) {
				this.$refs.board.$refs.drawingboard.startDrawing(e.x, e.y)
			},

			onRemoteContinueDrawing(e) {
				this.$refs.board.$refs.drawingboard.continueDrawing(e.x, e.y)
			},

			onRemoteStopDrawing(e) {
				this.$refs.board.$refs.drawingboard.stopDrawing()
			},

			onRemoteCanvasDimensions(e) {
				this.$refs.board.$refs.drawingboard.canvasDimensions(e)
			}
		}
	}
</script>
