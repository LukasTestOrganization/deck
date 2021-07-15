<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
	<div v-if="copiedCard">
		<div class="section-wrapper">
			<div v-tooltip="t('deck', 'Tags')" class="section-label icon-tag">
				<span class="hidden-visually">{{ t('deck', 'Tags') }}</span>
			</div>
			<div class="section-details">
				<Multiselect v-model="assignedLabels"
					:multiple="true"
					:disabled="!canEdit"
					:options="labelsSorted"
					:placeholder="t('deck', 'Assign a tag to this card…')"
					:taggable="true"
					label="title"
					track-by="id"
					@select="addLabelToCard"
					@remove="removeLabelFromCard">
					<template #option="scope">
						<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
							{{ scope.option.title }}
						</div>
					</template>
					<template #tag="scope">
						<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
							{{ scope.option.title }}
						</div>
					</template>
				</Multiselect>
			</div>
		</div>

		<div class="section-wrapper">
			<div v-tooltip="t('deck', 'Assign to users')" class="section-label icon-group">
				<span class="hidden-visually">{{ t('deck', 'Assign to users/groups/circles') }}</span>
			</div>
			<div class="section-details">
				<Multiselect v-if="canEdit"
					v-model="assignedUsers"
					:multiple="true"
					:options="formatedAssignables"
					:user-select="true"
					:auto-limit="false"
					:placeholder="t('deck', 'Assign a user to this card…')"
					label="displayname"
					track-by="multiselectKey"
					@select="assignUserToCard"
					@remove="removeUserFromCard">
					<template #tag="scope">
						<div class="avatarlist--inline">
							<Avatar :user="scope.option.uid"
								:display-name="scope.option.displayname"
								:size="24"
								:is-no-user="scope.option.isNoUser"
								:disable-menu="true" />
						</div>
					</template>
				</Multiselect>
				<div v-else class="avatar-list--readonly">
					<Avatar v-for="option in assignedUsers"
						:key="option.primaryKey"
						:user="option.uid"
						:display-name="option.displayname"
						:is-no-user="option.isNoUser"
						:size="32" />
				</div>
			</div>
		</div>

		<div class="section-wrapper">
			<div v-tooltip="t('deck', 'Due date')" class="section-label icon-calendar-dark">
				<span class="hidden-visually">{{ t('deck', 'Due date') }}</span>
			</div>
			<div class="section-details">
				<DatetimePicker v-model="duedate"
					:placeholder="t('deck', 'Set a due date')"
					type="datetime"
					:minute-step="5"
					:show-second="false"
					:lang="lang"
					:formatter="format"
					:disabled="saving || !canEdit"
					:shortcuts="shortcuts"
					confirm />
				<Actions v-if="canEdit">
					<ActionButton v-if="copiedCard.duedate" icon="icon-delete" @click="removeDue()">
						{{ t('deck', 'Remove due date') }}
					</ActionButton>
				</Actions>
			</div>
		</div>

		<div class="section-wrapper">
			<CollectionList v-if="card.id"
				:id="`${card.id}`"
				:name="card.title"
				type="deck-card" />
		</div>

		<Description :key="card.id" :card="card" @change="descriptionChanged" />
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import moment from '@nextcloud/moment'
import { Avatar, Actions, ActionButton, Multiselect, DatetimePicker } from '@nextcloud/vue'

import { CollectionList } from 'nextcloud-vue-collections'
import Color from '../../mixins/color'
import {
	getLocale,
	getDayNamesMin,
	getFirstDay,
	getMonthNamesShort,
} from '@nextcloud/l10n'
import Description from './Description'

export default {
	name: 'CardSidebarTabDetails',
	components: {
		Description,
		Multiselect,
		DatetimePicker,
		Actions,
		ActionButton,
		Avatar,
		CollectionList,
	},
	mixins: [Color],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			saving: false,
			assignedUsers: null,
			addedLabelToCard: null,
			copiedCard: null,
			assignedLabels: null,
			locale: getLocale(),
			lang: {
				days: getDayNamesMin(),
				months: getMonthNamesShort(),
				formatLocale: {
					firstDayOfWeek: getFirstDay() === 0 ? 7 : getFirstDay(),
				},
				placeholder: {
					date: t('deck', 'Select Date'),
				},
			},
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
			shortcuts: [
				{
					text: t('deck', 'Today'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate())
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Tomorrow'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 1)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Next week'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 7)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Next month'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 30)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
			],
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			cardDetailsInModal: state => state.cardDetailsInModal,
		}),
		...mapGetters(['canEdit', 'assignables']),
		formatedAssignables() {
			return this.assignables.map(item => {
				const assignable = {
					...item,
					user: item.primaryKey,
					displayName: item.displayname,
					icon: 'icon-user',
					isNoUser: false,
					multiselectKey: item.type + ':' + item.uid,
				}

				if (item.type === 1) {
					assignable.icon = 'icon-group'
					assignable.isNoUser = true
				}
				if (item.type === 7) {
					assignable.icon = 'icon-circles'
					assignable.isNoUser = true
				}

				return assignable
			})
		},
		duedate: {
			get() {
				return this.card.duedate ? new Date(this.card.duedate) : null
			},
			async set(val) {
				this.saving = true
				await this.$store.dispatch('updateCardDue', {
					...this.copiedCard,
					duedate: val ? (new Date(val)).toISOString() : null,
				})
				this.saving = false
			},
		},

		labelsSorted() {
			return [...this.currentBoard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
	},
	watch: {
		card() {
			this.initialize()
		},
	},
	mounted() {
		this.initialize()
	},
	methods: {
		descriptionChanged(newDesc) {
			this.copiedCard.description = newDesc
		},
		async initialize() {
			if (!this.card) {
				return
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.card))
			this.assignedLabels = [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)

			if (this.card.assignedUsers && this.card.assignedUsers.length > 0) {
				this.assignedUsers = this.card.assignedUsers.map((item) => ({
					...item.participant,
					isNoUser: item.participant.type !== 0,
					multiselectKey: item.participant.type + ':' + item.participant.primaryKey,
				}))
			} else {
				this.assignedUsers = []
			}
		},

		removeDue() {
			this.copiedCard.duedate = null
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},

		assignUserToCard(user) {
			this.$store.dispatch('assignCardToUser', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},

		removeUserFromCard(user) {
			this.$store.dispatch('removeUserFromCard', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},

		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			const data = {
				card: this.copiedCard,
				labelId: newLabel.id,
			}
			this.$store.dispatch('addLabel', data)
		},

		removeLabelFromCard(removedLabel) {

			const removeIndex = this.copiedCard.labels.findIndex((label) => {
				return label.id === removedLabel.id
			})
			if (removeIndex !== -1) {
				this.copiedCard.labels.splice(removeIndex, 1)
			}

			const data = {
				card: this.copiedCard,
				labelId: removedLabel.id,
			}
			this.$store.dispatch('removeLabel', data)
		},
		stringify(date) {
			return moment(date).locale(this.locale).format('LLL')
		},
		parse(value) {
			return moment(value).toDate()
		},
	},
}
</script>
<style lang="scss" scoped>

.section-wrapper::v-deep .mx-datepicker-main.mx-datepicker-popup {
	left: 0 !important;
}

.section-wrapper::v-deep .mx-datepicker-main.mx-datepicker-popup.mx-datepicker-sidebar {
	padding: 0 !important;
}

.section-wrapper {
	display: flex;
	max-width: 100%;
	margin-top: 10px;

	.section-label {
		background-position: 0px center;
		width: 28px;
		margin-left: 9px;
		flex-shrink: 0;
	}

	.section-details {
		flex-grow: 1;

		button.action-item--single {
			margin-top: -6px;
		}
	}
}

.tag {
	flex-grow: 0;
	flex-shrink: 1;
	overflow: hidden;
	padding: 0px 5px;
	border-radius: 15px;
	font-size: 85%;
	margin-right: 3px;
}

.avatarLabel {
	padding: 6px
}

.section-details::v-deep .multiselect__tags-wrap {
	flex-wrap: wrap;
}

.avatar-list--readonly .avatardiv {
	margin-right: 3px;
}

.avatarlist--inline {
	display: flex;
	align-items: center;
	margin-right: 3px;
	.avatarLabel {
		padding: 0;
	}
}

.multiselect::v-deep .multiselect__tags-wrap {
	z-index: 2;
}

.multiselect.multiselect--active::v-deep .multiselect__tags-wrap {
	z-index: 0;
}

</style>
