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

import 'url-search-params-polyfill'

import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { BoardApi } from '../services/BoardApi'
import actions from './actions'
import stack from './stack'
import card from './card'
import comment from './comment'
import trashbin from './trashbin'
import attachment from './attachment'
import overview from './overview'
Vue.use(Vuex)

const apiClient = new BoardApi()
const debug = process.env.NODE_ENV !== 'production'

export const BOARD_FILTERS = {
	ALL: '',
	ARCHIVED: 'archived',
	SHARED: 'shared',
}

export default new Vuex.Store({
	modules: {
		actions,
		stack,
		card,
		comment,
		trashbin,
		attachment,
		overview,
	},
	strict: debug,
	state: {
		config: loadState('deck', 'config', {}),
		showArchived: false,
		navShown: localStorage.getItem('deck.navShown') === 'true',
		compactMode: localStorage.getItem('deck.compactMode') === 'true',
		cardDetailsInModal: localStorage.getItem('deck.cardDetailsInModal') === 'true',
		sidebarShown: false,
		currentBoard: null,
		currentCard: null,
		boards: [],
		sharees: [],
		assignableUsers: [],
		boardFilter: BOARD_FILTERS.ALL,
		searchQuery: '',
		activity: [],
		activityLoadMore: true,
		filter: { tags: [], users: [], due: '' },
	},
	getters: {
		config: state => (key) => {
			return state.config[key]
		},
		cardDetailsInModal: state => {
			return state.cardDetailsInModal
		},
		getSearchQuery: state => {
			return state.searchQuery
		},
		getFilter: state => {
			return state.filter
		},
		boards: state => {
			return state.boards
		},
		boardById: state => (id) => {
			return state.boards.find((board) => board.id === id)
		},
		assignables: state => {
			return [
				...state.assignableUsers.map((user) => ({ ...user, type: 0 })),
				...state.currentBoard.acl.filter((acl) => acl.type === 1 && typeof acl.participant === 'object').map((group) => ({ ...group.participant, type: 1 })),
				...state.currentBoard.acl.filter((acl) => acl.type === 7 && typeof acl.participant === 'object').map((circle) => ({ ...circle.participant, type: 7 })),
			]
		},
		noneArchivedBoards: state => {
			return state.boards.filter(board => {
				return board.archived === false && !board.deletedAt
			})
		},
		archivedBoards: state => {
			return state.boards.filter(board => {
				return board.archived === true && !board.deletedAt
			})
		},
		sharedBoards: state => {
			return state.boards.filter(board => {
				return board.shared && !board.deletedAt
			})
		},
		filteredBoards: state => {
			// filters the boards depending on the active filter
			const boards = state.boards.filter(board => {
				return (state.boardFilter === BOARD_FILTERS.ALL && board.archived === false)
					|| (state.boardFilter === BOARD_FILTERS.ARCHIVED && board.archived === true)
					|| (state.boardFilter === BOARD_FILTERS.SHARED && board.shared === 1)
			})
			return boards
		},
		currentBoardLabels: state => {
			return state.currentBoard ? state.currentBoard.labels : []
		},
		canEdit: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_EDIT : false
		},
		canManage: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_MANAGE : false
		},
		canShare: state => {
			return state.currentBoard ? state.currentBoard.permissions.PERMISSION_SHARE : false
		},
		isArchived: state => {
			return state.currentBoard && state.currentBoard.archived
		},
	},
	mutations: {
		SET_CONFIG(state, { key, value }) {
			const [scope, id, configKey] = key.split(':', 3)
			let indexExisting = -1
			switch (scope) {
			case 'board':
				indexExisting = state.boards.findIndex((b) => {
					return id === '' + b.id
				})

				if (indexExisting > -1) {
					Vue.set(state.boards[indexExisting].settings, configKey, value)
				}
				break
			default:
				Vue.set(state.config, key, value)
			}
		},
		setSearchQuery(state, searchQuery) {
			state.searchQuery = searchQuery
		},
		SET_FILTER(state, filter) {
			Object.assign(state.filter, filter)
		},
		TOGGLE_FILTER(state, filter) {
			Object.keys(filter).forEach((key) => {
				switch (key) {
				case 'due':
					Vue.set(state.filter, key, filter.due)
					break
				default:
					filter[key].forEach((item) => {
						if (state.filter[key].indexOf(item) === -1) {
							state.filter[key].push(item)
						} else {
							state.filter[key].splice(state.filter[key].indexOf(item), 1)
						}
					})
					break
				}
			})
		},
		toggleShowArchived(state) {
			state.showArchived = !state.showArchived
		},
		/*
		 * Adds or replaces a board in the store.
		 * Matches a board by it's id.
		 */
		addBoard(state, board) {
			const indexExisting = state.boards.findIndex((b) => {
				return board.id === b.id
			})

			if (indexExisting > -1) {
				Vue.set(state.boards, indexExisting, board)
			} else {
				state.boards.push(board)
			}
		},

		cloneBoard(state, board) {
			const indexExisting = state.boards.findIndex((b) => {
				return board.id === b.id
			})

			if (indexExisting > -1) {
				Vue.set(state.boards, indexExisting, board)
			} else {
				state.boards.push(board)
			}
		},

		/*
		 * Removes the board from the store.
		 */
		removeBoard(state, board) {
			state.boards = state.boards.filter((b) => {
				return board.id !== b.id
			})
		},
		toggleNav(state, navState) {
			state.navShown = navState
			localStorage.setItem('deck.navShown', navState)
		},
		toggleSidebar(state) {
			state.sidebarShown = !state.sidebarShown
		},
		toggleCompactMode(state) {
			state.compactMode = !state.compactMode
			localStorage.setItem('deck.compactMode', state.compactMode)
		},
		setCardDetailsInModal(state) {
			state.cardDetailsInModal = !state.cardDetailsInModal
			localStorage.setItem('deck.cardDetailsInModal', state.cardDetailsInModal)
		},
		setBoards(state, boards) {
			state.boards = boards
		},
		setSharees(state, shareesUsersAndGroups) {
			Vue.set(state, 'sharees', shareesUsersAndGroups.exact.users)
			state.sharees.push(...shareesUsersAndGroups.exact.groups)
			state.sharees.push(...shareesUsersAndGroups.exact.circles)

			state.sharees.push(...shareesUsersAndGroups.users)
			state.sharees.push(...shareesUsersAndGroups.groups)
			state.sharees.push(...shareesUsersAndGroups.circles)
		},
		setAssignableUsers(state, users) {
			state.assignableUsers = users
		},
		setBoardFilter(state, filter) {
			state.boardFilter = filter
		},
		setCurrentBoard(state, board) {
			state.currentBoard = board
		},
		setCurrentCard(state, card) {
			state.currentCard = card
		},

		// label mutators
		removeLabelFromCurrentBoard(state, labelId) {
			const removeIndex = state.currentBoard.labels.findIndex((l) => {
				return labelId === l.id
			})

			if (removeIndex > -1) {
				state.currentBoard.labels.splice(removeIndex, 1)
			}
		},
		updateLabelFromCurrentBoard(state, newLabel) {
			const labelToUpdate = state.currentBoard.labels.find((l) => {
				return newLabel.id === l.id
			})

			labelToUpdate.title = newLabel.title
			labelToUpdate.color = newLabel.color
		},
		addLabelToCurrentBoard(state, newLabel) {

			state.currentBoard.labels.push(newLabel)
		},

		// acl mutators
		addAclToCurrentBoard(state, createdAcl) {
			state.currentBoard.acl.push(createdAcl)
		},
		updateAclFromCurrentBoard(state, acl) {
			for (const acl_ in state.currentBoard.acl) {
				if (state.currentBoard.acl[acl_].participant.uid === acl.participant.uid) {
					Vue.set(state.currentBoard.acl, acl_, acl)
					break
				}
			}
		},
		deleteAclFromCurrentBoard(state, acl) {
			let removeIndex = -1
			for (const index in state.currentBoard.acl) {
				const attr = state.currentBoard.acl[index]
				if (acl.id === attr.id) {
					removeIndex = index
					break
				}
			}

			if (removeIndex > -1) {
				Vue.delete(state.currentBoard.acl, removeIndex)
			}
		},
		toggleCoverImages(state, board) {
			let currentBoard = state.boards.filter((b) => {
				return board.id === b.id
			})
			currentBoard = currentBoard[0]
			Vue.set(currentBoard, 'coverImages', board.coverImages)
		},

	},
	actions: {
		async setConfig({ commit }, config) {
			for (const key in config) {
				try {
					await axios.post(generateOcsUrl(`apps/deck/api/v1.0/config/${key}`), {
						value: config[key],
					})
					commit('SET_CONFIG', { key, value: config[key] })
				} catch (e) {
					console.error(`Error while saving ${key}`, e.response)
					throw e
				}
			}
		},
		setFilter({ commit }, filter) {
			commit('SET_FILTER', filter)
		},
		toggleFilter({ commit }, filter) {
			commit('TOGGLE_FILTER', filter)
		},
		async loadBoardById({ commit, dispatch }, boardId) {
			const filterReset = { tags: [], users: [], due: '' }
			dispatch('setFilter', filterReset)
			commit('setCurrentBoard', null)
			const board = await apiClient.loadById(boardId)
			commit('setCurrentBoard', board)
			commit('setAssignableUsers', board.users)
		},

		async refreshBoard({ commit }, boardId) {
			const board = await apiClient.loadById(boardId)
			commit('setCurrentBoard', board)
			commit('setAssignableUsers', board.users)
		},

		toggleShowArchived({ commit }) {
			commit('toggleShowArchived')
		},

		/**
		 * @param commit
		 * @param state
		 * @param {Board} board
		 */
		archiveBoard({ commit }, board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = true
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		/**
		 * @param commit
		 * @param state
		 * @param {Board} board
		 */
		unarchiveBoard({ commit }, board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.archived = false
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		/**
		 * Updates a board API side.
		 *
		 * @param commit
		 * @param board The board to update.
		 * @returns {Promise<void>}
		 */
		async updateBoard({ commit }, board) {
			const storedBoard = await apiClient.updateBoard(board)
			commit('addBoard', storedBoard)
		},
		createBoard({ commit }, boardData) {
			apiClient.createBoard(boardData)
				.then((board) => {
					commit('addBoard', board)
				})
		},
		async cloneBoard({ commit }, boardData) {
			try {
				const newBoard = await apiClient.cloneBoard(boardData)
				commit('cloneBoard', newBoard)
				return newBoard
			} catch (err) {
				return err
			}
		},
		removeBoard({ commit }, board) {
			commit('removeBoard', board)
		},
		async loadBoards({ commit }) {
			const boards = await apiClient.loadBoards()
			commit('setBoards', boards)
		},
		async loadSharees({ commit }, query) {
			const params = new URLSearchParams()
			if (typeof query === 'undefined') {
				return
			}
			params.append('search', query)
			params.append('format', 'json')
			params.append('perPage', 20)
			params.append('itemType', [0, 1, 4, 7])
			params.append('lookup', false)

			const response = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), { params })
			commit('setSharees', response.data.ocs.data)
		},

		setBoardFilter({ commmit }, filter) {
			commmit('setBoardFilter', filter)
		},
		toggleNav({ commit }, navState) {
			commit('toggleNav', navState)
		},
		toggleSidebar({ commit }) {
			commit('toggleSidebar')
		},
		toggleCompactMode({ commit }) {
			commit('toggleCompactMode')
		},
		setCardDetailsInModal({ commit }, show) {
			commit('setCardDetailsInModal', show)
		},
		setCurrentBoard({ commit }, board) {
			commit('setCurrentBoard', board)
		},
		setAssignableUsers({ commit }, board) {
			commit('setAssignableUsers', board)
		},
		setCurrentCard({ commit }, card) {
			commit('setCurrentCard', card)
		},

		// label actions
		removeLabelFromCurrentBoard({ commit }, label) {
			apiClient.deleteLabel(label)
				.then((label) => {
					commit('removeLabelFromCurrentBoard', label.id)
				})
		},
		updateLabelFromCurrentBoard({ commit }, newLabel) {
			apiClient.updateLabel(newLabel)
				.then((newLabel) => {
					commit('updateLabelFromCurrentBoard', newLabel)
				})
		},
		addLabelToCurrentBoard({ commit }, newLabel) {
			newLabel.boardId = this.state.currentBoard.id
			apiClient.createLabel(newLabel)
				.then((newLabel) => {
					commit('addLabelToCurrentBoard', newLabel)
				})
		},

		// acl actions
		async addAclToCurrentBoard({ dispatch, commit }, newAcl) {
			newAcl.boardId = this.state.currentBoard.id
			const result = await apiClient.addAcl(newAcl)
			commit('addAclToCurrentBoard', result)
			dispatch('refreshBoard', newAcl.boardId)
		},
		updateAclFromCurrentBoard({ commit }, acl) {
			acl.boardId = this.state.currentBoard.id
			apiClient.updateAcl(acl)
				.then((acl) => {
					commit('updateAclFromCurrentBoard', acl)
				})
		},
		deleteAclFromCurrentBoard({ dispatch, commit }, acl) {
			acl.boardId = this.state.currentBoard.id
			apiClient.deleteAcl(acl)
				.then((acl) => {
					commit('deleteAclFromCurrentBoard', acl)
					dispatch('loadBoardById', acl.boardId)
				})
		},
		toggleCoverImages({ commit }, board) {
			const boardCopy = JSON.parse(JSON.stringify(board))
			boardCopy.coverImages = !boardCopy.coverImages
			apiClient.updateBoard(boardCopy)
				.then((board) => {
					commit('toggleCoverImages', board)
				})
		},
	},
})
