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
	<DashboardWidget :items="cards"
		empty-content-icon="icon-deck"
		:empty-content-message="t('deck', 'No upcoming cards')"
		:show-more-text="t('deck', 'upcoming cards')"
		:show-more-url="showMoreUrl"
		:loading="loading"
		@hide="() => {}"
		@markDone="() => {}">
		<template v-slot:default="{ item }">
			<a :key="item.id"
				:href="cardLink(item)"
				target="_blank"
				class="card">
				<div class="card--header">
					<DueDate class="right" :card="item" />
					<span class="title">{{ item.title }}</span>
				</div>
				<ul v-if="item.labels && item.labels.length"
					class="labels">
					<li v-for="label in item.labels" :key="label.id" :style="labelStyle(label)">
						<span>{{ label.title }}</span>
					</li>
				</ul>
			</a>
		</template>
	</DashboardWidget>
</template>

<script>
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { mapGetters } from 'vuex'
import labelStyle from './../mixins/labelStyle'
import DueDate from '../components/cards/badges/DueDate'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'DashboardUpcoming',
	components: {
		DueDate,
		DashboardWidget,
	},
	mixins: [ labelStyle ],
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
		]),
		cards() {
			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return card.duedate !== null
			})
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list
		},
		cardLink() {
			return (card) => {
				return generateUrl('/apps/deck') + `#/board/${card.boardId}/card/${card.id}`
			}
		},
		showMoreUrl() {
			return this.cards.length > 7 ? generateUrl('/apps/deck') : null
		},
	},
	beforeMount() {
		this.loading = true
		this.$store.dispatch('loadUpcoming').then(() => {
			this.loading = false
		})
	},
}
</script>

<style lang="scss" scoped>
	@import './../css/labels';

	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 8px;
		height: 60px;

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.card--header {
		overflow: hidden;
		.title {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			display: block;
		}
	}

	.labels {
		margin-left: 0;
	}

	.duedate::v-deep {
		.due {
			margin: 0 0 0 10px;
			padding: 2px 4px;
			font-size: 90%;
		}
	}

	.right {
		float: right;
	}
</style>
