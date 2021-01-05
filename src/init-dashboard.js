/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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

import Vue from 'vue'
import Vuex from 'vuex'

import overview from './store/overview'

import './css/dashboard.scss'

import DashboardUpcoming from './views/DashboardUpcoming.vue'
import DashboardToday from './views/DashboardToday.vue'
import DashboardTomorrow from './views/DashboardTomorrow.vue'

Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

const store = new Vuex.Store({
	modules: {
		overview,
	},
	strict: debug,
})

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken);
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('deck', 'js/');

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('deck', (el) => {
		const View = Vue.extend(DashboardUpcoming)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})

	OCA.Dashboard.register('deckToday', (el) => {
		const View = Vue.extend(DashboardToday)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})
	OCA.Dashboard.register('deckTomorrow', (el) => {
		const View = Vue.extend(DashboardTomorrow)
		const vm = new View({
			propsData: {},
			store,
		}).$mount(el)
		return vm
	})
})
