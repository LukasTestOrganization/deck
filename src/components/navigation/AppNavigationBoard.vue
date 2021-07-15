<!--
  - @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<AppNavigationItem v-if="!editing"
		:title="!deleted ? board.title : undoText"
		:loading="loading"
		:to="routeTo"
		:undo="deleted"
		@undo="unDelete">
		<AppNavigationIconBullet slot="icon" :color="board.color" />

		<AppNavigationCounter v-if="board.acl.length"
			slot="counter"
			class="icon-shared"
			style="opacity: 0.5" />
		<template v-if="!deleted" slot="actions">
			<template v-if="!isDueSubmenuActive">
				<ActionButton
					icon="icon-info"
					:close-after-click="true"
					@click="actionDetails">
					{{ t('deck', 'Board details') }}
				</ActionButton>
				<ActionButton v-if="canManage && !board.archived"
					icon="icon-rename"
					:close-after-click="true"
					@click="actionEdit">
					{{ t('deck', 'Edit board') }}
				</ActionButton>
				<ActionButton v-if="canManage && !board.archived"
					icon="icon-clone"
					:close-after-click="true"
					@click="actionClone">
					{{ t('deck', 'Clone board') }}
				</ActionButton>
				<ActionButton v-if="canManage && board.archived"
					icon="icon-archive"
					:close-after-click="true"
					@click="actionUnarchive">
					{{ t('deck', 'Unarchive board') }}
				</ActionButton>
				<ActionButton v-else-if="canManage && !board.archived"
					icon="icon-archive"
					:close-after-click="true"
					@click="actionArchive">
					{{ t('deck', 'Archive board') }}
				</ActionButton>

				<ActionButton v-if="!board.archived && board.acl.length === 0" :icon="board.settings['notify-due'] === 'off' ? 'icon-sound' : 'icon-sound-off'" @click="board.settings['notify-due'] === 'off' ? updateSetting('notify-due', 'all') : updateSetting('notify-due', 'off')">
					{{ board.settings['notify-due'] === 'off' ? t('deck', 'Turn on due date reminders') : t('deck', 'Turn off due date reminders') }}
				</ActionButton>
			</template>

			<!-- Due date reminder settings -->
			<template v-if="isDueSubmenuActive">
				<ActionButton
					:icon="updateDueSetting ? 'icon-loading-small' : 'icon-view-previous'"
					:disabled="updateDueSetting"
					@click="isDueSubmenuActive=false">
					{{ t('deck', 'Due date reminders') }}
				</ActionButton>

				<ActionButton
					name="notification"
					icon="icon-sound"
					:disabled="updateDueSetting"
					:class="{ 'forced-active': board.settings['notify-due'] === 'all' }"
					@click="updateSetting('notify-due', 'all')">
					{{ t('deck', 'All cards') }}
				</ActionButton>
				<ActionButton
					name="notification"
					icon="icon-user"
					:disabled="updateDueSetting"
					:class="{ 'forced-active': board.settings['notify-due'] === 'assigned' }"
					@click="updateSetting('notify-due', 'assigned')">
					{{ t('deck', 'Assigned cards') }}
				</ActionButton>
				<ActionButton
					name="notification"
					icon="icon-sound-off"
					:disabled="updateDueSetting"
					:class="{ 'forced-active': board.settings['notify-due'] === 'off' }"
					@click="updateSetting('notify-due', 'off')">
					{{ t('deck', 'No notifications') }}
				</ActionButton>
			</template>
			<ActionButton v-else-if="!board.archived && board.acl.length > 0"
				:title="t('deck', 'Due date reminders')"
				:icon="dueDateReminderIcon"
				@click="isDueSubmenuActive=true">
				{{ dueDateReminderText }}
			</ActionButton>
			<ActionCheckbox v-if="canManage"
				:checked="board.coverImages"
				@change="actionToggleCoverImages">
				{{ t('deck', 'Show cover images') }}
			</ActionCheckbox>
			<ActionButton v-if="canManage && !isDueSubmenuActive"
				icon="icon-delete"
				:close-after-click="true"
				@click="actionDelete">
				{{ t('deck', 'Delete board') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
	<div v-else-if="editing" class="board-edit">
		<ColorPicker class="app-navigation-entry-bullet-wrapper" :value="`#${board.color}`" @input="updateColor">
			<div :style="{ backgroundColor: getColor }" class="color0 icon-colorpicker app-navigation-entry-bullet" />
		</ColorPicker>
		<form @submit.prevent.stop="applyEdit">
			<input v-model="editTitle" type="text" required>
			<input type="submit" value="" class="icon-confirm">
			<Actions><ActionButton icon="icon-close" @click.stop.prevent="cancelEdit" /></Actions>
		</form>
	</div>
</template>

<script>
import { AppNavigationIconBullet, AppNavigationCounter, AppNavigationItem, ColorPicker, Actions, ActionButton, ActionCheckbox } from '@nextcloud/vue'
import ClickOutside from 'vue-click-outside'

export default {
	name: 'AppNavigationBoard',
	components: {
		AppNavigationIconBullet,
		AppNavigationCounter,
		AppNavigationItem,
		ColorPicker,
		Actions,
		ActionButton,
		ActionCheckbox,
	},
	directives: {
		ClickOutside,
	},
	inject: [
		'boardApi',
	],
	props: {
		board: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			classes: [],
			deleted: false,
			loading: false,
			editing: false,
			menuOpen: false,
			undoTimeoutHandle: null,
			editTitle: '',
			editColor: '',
			isDueSubmenuActive: false,
			updateDueSetting: null,
		}
	},
	computed: {
		getColor() {
			if (this.editColor !== '') {
				return this.editColor
			}
			return this.board.color
		},
		undoText() {
			return t('deck', 'Board {0} deleted', [this.board.title])
		},
		routeTo() {
			return {
				name: 'board',
				params: { id: this.board.id },
			}
		},
		canManage() {
			return this.board.permissions.PERMISSION_MANAGE
		},
		dueDateReminderIcon() {
			if (this.board.settings['notify-due'] === 'all') {
				return 'icon-sound'
			} else if (this.board.settings['notify-due'] === 'assigned') {
				return 'icon-user'
			} else if (this.board.settings['notify-due'] === 'off') {
				return 'icon-sound-off'
			}
			return ''
		},
		dueDateReminderText() {
			if (this.board.settings['notify-due'] === 'all') {
				return t('deck', 'All cards')
			} else if (this.board.settings['notify-due'] === 'assigned') {
				return t('deck', 'Only assigned cards')
			} else if (this.board.settings['notify-due'] === 'off') {
				return t('deck', 'No reminder')
			}
			return ''
		},
	},
	watch: {},
	mounted() {
		// prevent click outside event with popupItem.
		this.popupItem = this.$el
	},
	methods: {
		unDelete() {
			clearTimeout(this.undoTimeoutHandle)
			this.boardApi.unDeleteBoard(this.board)
				.then(() => {
					this.deleted = false
				})
		},
		updateColor(newColor) {
			this.editColor = newColor
		},
		actionEdit() {
			this.editTitle = this.board.title
			this.editColor = '#' + this.board.color
			this.editing = true
		},
		async actionClone() {
			this.loading = true
			try {
				const newBoard = await this.$store.dispatch('cloneBoard', this.board)
				this.loading = false
				this.$router.push({ name: 'board', params: { id: newBoard.id } })
			} catch (e) {
				OC.Notification.showTemporary(t('deck', 'An error occurred'))
				console.error(e)
			}
		},
		actionArchive() {
			this.loading = true
			this.$store.dispatch('archiveBoard', this.board)
		},
		actionUnarchive() {
			this.loading = true
			this.$store.dispatch('unarchiveBoard', this.board)
		},
		actionDelete() {
			OC.dialogs.confirmDestructive(
				t('deck', 'Are you sure you want to delete the board {title}? This will delete all the data of this board.', { title: this.board.title }),
				t('deck', 'Delete the board?'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('deck', 'Delete'),
					confirmClasses: 'error',
					cancel: t('deck', 'Cancel'),
				},
				(result) => {
					if (result) {
						this.loading = true
						this.boardApi.deleteBoard(this.board)
							.then(() => {
								this.loading = false
								this.deleted = true
								this.undoTimeoutHandle = setTimeout(() => {
									this.$store.dispatch('removeBoard', this.board)
								}, 7000)
							})
					}
				},
				true
			)
		},
		actionDetails() {
			this.$router.push({ name: 'board.details', params: { id: this.board.id } })
		},
		applyEdit(e) {
			this.editing = false
			if (this.editTitle || this.editColor) {
				this.loading = true
				const copy = JSON.parse(JSON.stringify(this.board))
				copy.title = this.editTitle
				copy.color = (typeof this.editColor.hex !== 'undefined' ? this.editColor.hex : this.editColor).substring(1)
				this.$store.dispatch('updateBoard', copy)
					.then(() => {
						this.loading = false
					})
			}
		},
		cancelEdit(e) {
			this.editing = false
		},
		async updateSetting(key, value) {
			this.updateDueSetting = value
			const setting = {}
			setting['board:' + this.board.id + ':' + key] = value
			await this.$store.dispatch('setConfig', setting)
			this.isDueSubmenuActive = false
			this.updateDueSetting = null
		},
		actionToggleCoverImages() {
			this.$store.dispatch('toggleCoverImages', this.board)
		},
	},
}
</script>

<style lang="scss" scoped>
	.board-edit {
		margin-left: 44px;
		order: 1;
		display: flex;
		height: 44px;

		form {
			display: flex;
			flex-grow: 1;

			input[type='text'] {
				flex-grow: 1;
			}
		}
	}

	.app-navigation-entry-bullet-wrapper {
		width: 44px;
		height: 44px;
		.color0 {
			width: 30px !important;
			margin: 5px;
			margin-left: 7px;
			height: 30px;
			border-radius: 50%;
			background-size: 14px;
		}
	}

	.forced-active {
		box-shadow: inset 4px 0 var(--color-primary-element);
	}
</style>
