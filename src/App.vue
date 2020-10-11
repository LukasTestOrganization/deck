<!--
 - @copyright Copyright (c) 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 -
 - @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
	<div id="content" :class="{ 'nav-hidden': !navShown, 'sidebar-hidden': !sidebarRouterView }">
		<AppNavigation v-show="navShown" />
		<div id="app-content" @click="clickCloseSidebar()">
			<router-view />
		</div>

		<Modal v-if="cardDetailsInModal && $route.params.cardId" :title="t('deck', 'Card details')" @close="hideModal()">
			<div class="modal__content modal__card">
				<router-view name="sidebar" />
			</div>
		</Modal>

		<router-view v-show="!cardDetailsInModal || !$route.params.cardId" name="sidebar" />
	</div>
</template>

<script>

import { mapState } from 'vuex'
import AppNavigation from './components/navigation/AppNavigation'
import { Modal } from '@nextcloud/vue'
import { BoardApi } from './services/BoardApi'

const boardApi = new BoardApi()

export default {
	name: 'App',
	components: {
		AppNavigation,
		Modal,
	},
	data() {
		return {
			addButton: {
				icon: 'icon-add',
				classes: [],
				text: t('deck', 'Add board'),
				edit: {
					text: t('deck', 'Add board'),
					action: () => {
					},
					reset: () => {
					},
				},
				action: () => {
					this.addButton.classes.push('editing')
				},
			},
		}
	},
	computed: {
		...mapState({
			navShown: state => state.navShown,
			sidebarShownState: state => state.sidebarShown,
			currentBoard: state => state.currentBoard,
			cardDetailsInModal: state => state.cardDetailsInModal,
		}),
		// TODO: properly handle sidebar showing for route subview and board sidebar
		sidebarRouterView() {
			// console.log(this.$route)
			return this.$route.name === 'card' || this.$route.name === 'board.details'
		},
		sidebarShown() {
			return this.sidebarRouterView || this.sidebarShownState
		},
	},
	created() {
		this.$store.dispatch('loadBoards')
		this.$store.dispatch('loadSharees')
	},
	methods: {
		hideModal() {
			this.$router.push({ name: 'board' })
		},
		clickCloseSidebar() {
			if (this.sidebarShown) {
				this.$router.push({ name: 'board' })
			}
		},
	},
	provide() {
		return {
			boardApi,
		}
	},
}
</script>

<style lang="scss" scoped>

	#content {
		#app-content {
			transition: margin-left 100ms ease;
			position: relative;
			overflow-x: hidden;
			align-items: stretch;
		}

		#app-sidebar {
			transition: max-width 100ms ease;
		}

		&.nav-hidden {
			#app-content {
				margin-left: 0;
			}
		}

		&.sidebar-hidden {
			#app-sidebar {
				max-width: 0;
				min-width: 0;
			}
		}
	}
</style>

<style lang="scss">

	.multiselect {
		width: 100%;
	}

	.modal__card {
		min-width: 320px;
		width: 50vw;
		max-width: 800px;
		min-height: 200px;
		height: 80vh;
	}
</style>
