/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { CardApi } from './../services/CardApi'
import Vue from 'vue'

const apiClient = new CardApi()

export default {
	state: {
		cards: [],
	},
	getters: {
		cardsByStack: (state, getters, rootState) => (id) => {
			return state.cards.filter((card) => {
				const { tags, users, due } = rootState.filter
				let allTagsMatch = true
				let allUsersMatch = true

				if (tags.length > 0) {
					tags.forEach((tag) => {
						if (card.labels.findIndex((l) => l.id === tag) === -1) {
							allTagsMatch = false
						}
					})
					if (!allTagsMatch) {
						return false
					}
				}

				if (users.length > 0) {
					users.forEach((user) => {
						if (card.assignedUsers.findIndex((u) => u.participant.uid === user) === -1) {
							allUsersMatch = false
						}
					})
					if (!allUsersMatch) {
						return false
					}
				}

				if (due !== '') {
					const datediffHour = ((new Date(card.duedate) - new Date()) / 3600000)
					switch (due) {
					case 'noDue':
						return (card.duedate === null)
					case 'overdue':
						return (card.overdue === 3)
					case 'dueToday':
						return (card.overdue >= 2)
					case 'dueWeek':
						return (datediffHour <= 168 && card.duedate !== null)
					case 'dueMonth':
						return (datediffHour <= 5040 && card.duedate !== null)
					}
				}

				return true
			})
				.filter((card) => card.stackId === id && (getters.getSearchQuery === ''
					|| (card.title.toLowerCase().includes(getters.getSearchQuery.toLowerCase())
					|| card.description.toLowerCase().includes(getters.getSearchQuery.toLowerCase()))
						.sort((a, b) => a.order - b.order)))
		},
		cardById: state => (id) => {
			return state.cards.find((card) => card.id === id)
		},
	},
	mutations: {
		clearCards(state) {
			state.cards = []
		},
		addCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				const existingCard = state.cards.find(_card => _card.id === card.id)
				Vue.set(state.cards, existingIndex, Object.assign({}, existingCard, card))
			} else {
				state.cards.push(card)
			}
		},
		deleteCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.cards.splice(existingIndex, 1)
			}
		},
		updateCard(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				Vue.set(state.cards, existingIndex, Object.assign({}, state.cards[existingIndex], card))
			}
		},
		updateCardsReorder(state, cards) {
			for (const newCard of cards) {
				const existingIndex = state.cards.findIndex(_card => _card.id === newCard.id)
				if (existingIndex !== -1) {
					const newCardObject = Object.assign({}, state.cards[existingIndex], { id: newCard.id, order: newCard.order, stackId: newCard.stackId })
					Vue.set(state.cards, existingIndex, newCardObject)
				}
			}
		},
		updateTitle(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.cards[existingIndex].title = card.title
			}
		},
		assignCardToUser(state, user) {
			const existingIndex = state.cards.findIndex(_card => _card.id === user.cardId)
			if (existingIndex !== -1) {
				state.cards[existingIndex].assignedUsers.push(user)
			}
		},
		removeUserFromCard(state, user) {
			const existingIndex = state.cards.findIndex(_card => _card.id === user.cardId)
			if (existingIndex !== -1) {
				const foundIndex = state.cards[existingIndex].assignedUsers.findIndex(_user => _user.id === user.id)
				if (foundIndex !== -1) {
					state.cards[existingIndex].assignedUsers.splice(foundIndex, 1)
				}
			}
		},
		updateCardDesc(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.cards[existingIndex].description = card.description
			}
		},
		updateCardDue(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				state.cards[existingIndex].duedate = card.duedate
			}
		},
		updateCardLabels(state, card) {
			const existingIndex = state.cards.findIndex(_card => _card.id === card.id)
			if (existingIndex !== -1) {
				const existingCard = state.cards.find(_card => _card.id === card.id)
				existingCard.labels = card.labels
			}
		},
	},
	actions: {
		addCard({ commit }, card) {
			apiClient.addCard(card)
				.then((createdCard) => {
					commit('addCard', createdCard)
				})
		},
		updateCard({ commit }, card) {
			apiClient.updateCard(card)
				.then((updatedCard) => {
					commit('updateTitle', updatedCard)
				})
		},
		moveCard({ commit }, card) {
			apiClient.updateCard(card)
				.then((updatedCard) => {
					commit('deleteCard', updatedCard)
				})
		},
		async reorderCard({ commit, getters }, card) {
			let i = 0
			for (const c of getters.cardsByStack(card.stackId)) {
				if (c.id === card.id) {
					await commit('updateCardsReorder', [card])
				}
				if (i === card.order) {
					i++
				}
				if (c.id !== card.id) {
					await commit('updateCardsReorder', [{ ...c, order: i++ }])
				}
			}
			await commit('updateCardsReorder', [card])

			const cards = await apiClient.reorderCard(card)
			await commit('updateCardsReorder', Object.values(cards))

		},
		deleteCard({ commit }, card) {
			apiClient.deleteCard(card.id)
				.then((card) => {
					commit('deleteCard', card)
				})
		},
		archiveUnarchiveCard({ commit }, card) {
			let call = 'archiveCard'
			if (card.archived === false) {
				call = 'unArchiveCard'
			}

			apiClient[call](card)
				.then((card) => {
					commit('deleteCard', card)
				})
		},
		assignCardToUser({ commit }, card) {
			apiClient.assignUser(card)
				.then((user) => {
					commit('assignCardToUser', user)
				})
		},
		removeUserFromCard({ commit }, card) {
			apiClient.removeUser(card)
				.then((user) => {
					commit('removeUserFromCard', user)
				})
		},
		addLabel({ commit }, data) {
			apiClient.assignLabelToCard(data)
				.then(() => {
					commit('updateCardLabels', data.card)
				})
		},
		removeLabel({ commit }, data) {
			apiClient.removeLabelFromCard(data)
				.then(() => {
					commit('updateCardLabels', data.card)
				})
		},
		cardUndoDelete({ commit }, card) {
			apiClient.updateCard(card)
				.then((card) => {
					commit('addCard', card)
				})
		},
		updateCardDesc({ commit }, card) {
			apiClient.updateCard(card)
				.then((updatedCard) => {
					commit('updateCardDesc', updatedCard)
				})
		},
		updateCardDue({ commit }, card) {
			apiClient.updateCard(card)
				.then((card) => {
					commit('updateCardDue', card)
				})
		},
	},
}
