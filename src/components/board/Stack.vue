<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  - @author Jakob Röhrl <jakob.roehrl@web.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="stack">
		<div v-click-outside="stopCardCreation" class="stack--header">
			<transition name="fade" mode="out-in">
				<h3 v-if="!canManage || isArchived">
					{{ stack.title }}
				</h3>
				<h3 v-else-if="!editing"
					v-tooltip="stack.title"
					class="stack--title"
					@click="startEditing(stack)">
					{{ stack.title }}
				</h3>
				<form v-else @submit.prevent="finishedEdit(stack)">
					<input v-model="copiedStack.title" v-focus type="text">
					<input v-tooltip="t('deck', 'Add a new list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</transition>
			<Actions v-if="canManage && !isArchived" :force-menu="true">
				<ActionButton icon="icon-archive" @click="modalArchivAllCardsShow=true">
					{{ t('deck', 'Archive all cards') }}
				</ActionButton>
				<ActionButton icon="icon-delete" @click="deleteStack(stack)">
					{{ t('deck', 'Delete list') }}
				</ActionButton>
			</Actions>
			<Actions v-if="canEdit && !showArchived && !isArchived">
				<ActionButton icon="icon-add" @click.stop="showAddCard=true">
					{{ t('deck', 'Add card') }}
				</ActionButton>
			</Actions>
		</div>

		<Modal v-if="modalArchivAllCardsShow" @close="modalArchivAllCardsShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Archive all cards in this list') }}</h3>
				<progress :value="stackTransfer.current" :max="stackTransfer.total" />
				<button class="primary" @click="archiveAllCardsFromStack(stack)">
					{{ t('deck', 'Archive all cards') }}
				</button>
				<button @click="modalArchivAllCardsShow=false">
					{{ t('deck', 'Cancel') }}
				</button>
			</div>
		</Modal>

		<transition name="slide-top" appear>
			<div v-if="showAddCard" class="stack--card-add">
				<form :class="{ 'icon-loading-small': stateCardCreating }"
					@submit.prevent.stop="clickAddCard()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add a new card') }}</label>
					<input id="new-stack-input-main"
						ref="newCardInput"
						v-model="newCardTitle"
						v-focus
						type="text"
						class="no-close"
						:disabled="stateCardCreating"
						placeholder="Add a new card"
						required
						@keydown.esc="stopCardCreation">

					<input v-show="!stateCardCreating"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
		</transition>

		<Container :get-child-payload="payloadForCard(stack.id)"
			group-name="stack"
			non-drag-area-selector=".dragDisabled"
			:drag-handle-selector="dragHandleSelector"
			@should-accept-drop="canEdit"
			@drop="($event) => onDropCard(stack.id, $event)">
			<Draggable v-for="card in cardsByStack" :key="card.id">
				<transition :appear="animate && !card.animated && (card.animated=true)"
					:appear-class="'zoom-appear-class'"
					:appear-active-class="'zoom-appear-active-class'">
					<CardItem :id="card.id" />
				</transition>
			</Draggable>
		</Container>
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import { Container, Draggable } from 'vue-smooth-dnd'

import { Actions, ActionButton, Modal } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import CardItem from '../cards/CardItem'

export default {
	name: 'Stack',
	components: {
		Actions,
		ActionButton,
		CardItem,
		Container,
		Draggable,
		Modal,
	},

	props: {
		stack: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			editing: false,
			copiedStack: '',
			newCardTitle: '',
			showAddCard: false,
			stateCardCreating: false,
			animate: false,
			modalArchivAllCardsShow: false,
			stackTransfer: {
				total: 0,
				current: null,
			},
		}
	},
	computed: {
		...mapGetters([
			'canManage',
			'canEdit',
			'isArchived',
		]),
		...mapState({
			showArchived: state => state.showArchived,
		}),
		cardsByStack() {
			return this.$store.getters.cardsByStack(this.stack.id)
		},
		dragHandleSelector() {
			return this.canEdit ? null : '.no-drag'
		},
	},

	methods: {
		stopCardCreation(e) {
			// For some reason the submit event triggers a MouseEvent that is bubbling to the outside
			// so we have to ignore it
			e.stopPropagation()
			if (this.$refs.newCardInput && this.$refs.newCardInput.parentElement === e.target.parentElement) {
				return false
			}
			this.showAddCard = false
			return false
		},
		async onDropCard(stackId, event) {
			const { addedIndex, removedIndex, payload } = event
			const card = Object.assign({}, payload)
			if (this.stack.id === stackId) {
				if (addedIndex !== null && removedIndex === null) {
					// move card to new stack
					card.stackId = stackId
					card.order = addedIndex
					console.debug('move card to stack', card.stackId, card.order)
					await this.$store.dispatch('reorderCard', card)
				}
				if (addedIndex !== null && removedIndex !== null) {
					card.order = addedIndex
					console.debug('move card in stack', card.stackId, card.order)
					await this.$store.dispatch('reorderCard', card)
				}
			}
		},
		payloadForCard(stackId) {
			return index => {
				return this.cardsByStack[index]
			}
		},
		deleteStack(stack) {
			this.$store.dispatch('deleteStack', stack)
		},
		archiveAllCardsFromStack(stack) {

			this.stackTransfer.total = this.cardsByStack.length
			this.cardsByStack.forEach((card, index) => {
				this.stackTransfer.current = index
				this.$store.dispatch('archiveUnarchiveCard', { ...card, archived: true })
			})
			this.modalArchivAllCardsShow = false
		},
		startEditing(stack) {
			this.copiedStack = Object.assign({}, stack)
			this.editing = true
		},
		finishedEdit(stack) {
			if (this.copiedStack.title !== stack.title) {
				this.$store.dispatch('updateStack', this.copiedStack)
			}
			this.editing = false
		},
		async clickAddCard() {
			this.stateCardCreating = true
			try {
				this.animate = true
				const newCard = await this.$store.dispatch('addCard', {
					title: this.newCardTitle,
					stackId: this.stack.id,
					boardId: this.stack.boardId,
				})
				this.newCardTitle = ''
				this.showAddCard = true
				this.$nextTick(() => {
					this.$refs.newCardInput.focus()
					this.animate = false
				})
				this.$router.push({ name: 'card', params: { cardId: newCard.id } })
			} catch (e) {
				showError('Could not create card: ' + e.response.data.message)
			} finally {
				this.stateCardCreating = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>

	@import './../../css/variables';

	.stack {
		width: $stack-width;
		margin-left: $stack-spacing;
		margin-right: $stack-spacing;
	}

	.stack--header {
		display: flex;
		position: sticky;
		top: 0;
		z-index: 100;
		padding: 3px;
		margin: 3px -3px;
		margin-right: -10px;
		margin-top: 0;
		margin-bottom: 3px;
		cursor: grab;

		h3, form {
			flex-grow: 1;
			display: flex;
			cursor: inherit;

			input[type=text] {
				flex-grow: 1;
			}
		}
	}

	.stack--title {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: calc($stack-width - 60px);
	}

	.stack--card-add {
		position: sticky;
		top: 52px;
		height: 52px;
		z-index: 100;
		display: flex;
		margin-left: -10px;
		margin-right: -10px;
		padding-top: 3px;

		form {
			display: flex;
			width: 100%;
			margin: 10px;
			margin-top: 0;
			margin-bottom: 10px;
			box-shadow: 0 0 3px var(--color-box-shadow);
			background-color: var(--color-main-background);
			border-radius: 3px;
		}

		&.icon-loading-small:after,
		&.icon-loading-small-dark:after {
			margin-left: calc(50% - 25px);
		}

		input[type=text] {
			flex-grow: 1;
		}

		input {
			border: none;
		}
	}

	.stack .smooth-dnd-container.vertical {
		margin-top: 3px;
	}

	/**
	 * Rules to handle scrolling behaviour are inherited from Board.vue
	 */

	.slide-top-enter-active,
	.slide-top-leave-active {
		transition: all 100ms ease;
	}

	.slide-top-enter, .slide-top-leave-to {
		transform: translateY(-10px);
		opacity: 0;
		height: 0px;
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		min-height: 100px;
		text-align: center;
		margin: 20px 20px 20px 20px;
	}

	.modal__content button {
		float: right;
	}

	progress {
		margin-top: 3px;
		margin-bottom: 30px;
	}

</style>
