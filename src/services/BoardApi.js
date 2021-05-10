/*
 * @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
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

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import './../models'

/**
 * This class handles all the api communication with the Deck backend.
 */
export class BoardApi {

	url(url) {
		url = `/apps/deck${url}`
		return generateUrl(url)
	}

	/**
	 * Updates a board.
	 *
	 * @param {Board} board the board object to update
	 * @returns {Promise}
	 */
	updateBoard(board) {
		return axios.put(this.url(`/boards/${board.id}`), board)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	/**
	 * Creates a new board.
	 * @typedef {Object} BoardCreateObject
	 * @property {string} title
	 * @property {string} color
	 *
	 * @param {BoardCreateObject} boardData The board data to send.
	 *        color the hexadecimal color value formated /[0-9A-F]{6}/i
	 * @returns {Promise}
	 */
	createBoard(boardData) {
		return axios.post(this.url('/boards'), boardData)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	deleteBoard(board) {
		return axios.delete(this.url(`/boards/${board.id}`))
			.then(
				() => {
					return Promise.resolve()
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	unDeleteBoard(board) {
		return axios.post(this.url(`/boards/${board.id}/deleteUndo`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	loadBoards() {
		return axios.get(this.url('/boards'))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	loadById(id) {
		return axios.get(this.url(`/boards/${id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	async cloneBoard(board) {
		try {
			const response = await axios.post(this.url(`/boards/${board.id}/clone`))
			return response.data
		} catch (err) {
			return err
		}
	}

	exportBoard(board) {
		return axios.get(this.url(`/boards/${board.id}/export`))
			.then(
				(response) => {
					const fields = { title: t('deck', 'Card title'), description: t('deck', 'Description'), stackId: t('deck', 'List name'), labels: t('deck', 'Tags'), duedate: t('deck', 'Due date'), createdAt: t('deck', 'Created'), lastModified: t('deck', 'Modified') }
					let row = ''
					Object.keys(fields).forEach(field => {
						row += '"' + fields[field] + '"' + '\t'
					})

					row = row.slice(0, -1)
					let CSV = row + '\r\n'

					response.data.stacks.forEach(stack => {
						stack.cards.forEach(card => {
							row = ''
							Object.keys(fields).forEach(field => {
								if (field === 'createdAt' || field === 'lastModified') {
									const date = new Date(Number(card[field]) * 1000)
									row += '"' + date.toLocaleDateString() + '"' + '\t'
								} else if (field === 'stackId') {
									row += '"' + stack.title + '"' + '\t'
								} else if (field === 'labels') {
									row += '"'
									card[field].forEach(label => {
										row += label.title + ', '
									})
									if (card[field].length > 0) {
										row = row.slice(0, -1)
									}
									row += '"' + '\t'
								} else {
									row += '"' + card[field] + '"' + '\t'
								}
							})
							row = row.slice(0, -1)
							CSV += row + '\r\n'
						})
					})
					let charCode = []
					const byteArray = []
					byteArray.push(255, 254)
					for (let i = 0; i < CSV.length; ++i) {
						charCode = CSV.charCodeAt(i)
						byteArray.push(charCode & 0xff)
						byteArray.push(charCode / 256 >>> 0)
					}
					const blob = new Blob([new Uint8Array(byteArray)], { type: 'text/csv;charset=UTF-16LE;' })
					const blobUrl = URL.createObjectURL(blob)
					const a = document.createElement('a')
					a.href = blobUrl // 'data:' + data
					a.download = response.data.title + '.csv'
					a.click()
					a.remove()
					return Promise.resolve()
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	// Label API Calls
	deleteLabel(id) {
		return axios.delete(this.url(`/labels/${id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	updateLabel(label) {
		return axios.put(this.url(`/labels/${label.id}`), label)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	createLabel(labelData) {
		return axios.post(this.url('/labels'), labelData)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	// Acl API Calls

	addAcl(acl) {
		return axios.post(this.url(`/boards/${acl.boardId}/acl`), acl)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	updateAcl(acl) {
		return axios.put(this.url(`/boards/${acl.boardId}/acl/${acl.id}`), acl)
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

	deleteAcl(acl) {
		return axios.delete(this.url(`/boards/${acl.boardId}/acl/${acl.id}`))
			.then(
				(response) => {
					return Promise.resolve(response.data)
				},
				(err) => {
					return Promise.reject(err)
				}
			)
			.catch((err) => {
				return Promise.reject(err)
			})
	}

}
