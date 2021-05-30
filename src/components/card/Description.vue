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
	<div>
		<h5>
			{{ t('deck', 'Description') }}
			<span v-if="descriptionLastEdit && !descriptionSaving">{{ t('deck', '(Unsaved)') }}</span>
			<span v-if="descriptionSaving">{{ t('deck', '(Saving…)') }}</span>
			<a v-tooltip="t('deck', 'Formatting help')"
				href="https://deck.readthedocs.io/en/latest/Markdown/"
				target="_blank"
				class="icon icon-info" />
			<Actions v-if="canEdit">
				<ActionButton v-if="!descriptionEditing" icon="icon-rename" @click="showEditor()">
					{{ t('deck', 'Edit description') }}
				</ActionButton>
				<ActionButton v-else icon="icon-toggle" @click="hideEditor()">
					{{ t('deck', 'View description') }}
				</ActionButton>
			</Actions>
			<Actions v-if="canEdit">
				<ActionButton v-if="descriptionEditing" icon="icon-attach" @click="showAttachmentModal()">
					{{ t('deck', 'Add Attachment') }}
				</ActionButton>
			</Actions>
		</h5>

		<div v-if="!descriptionEditing && hasDescription"
			id="description-preview"
			@click="clickedPreview"
			v-html="renderedDescription" />
		<p v-else-if="!descriptionEditing" class="placeholder" @click="showEditor()">
			{{ t('deck', 'Write a description …') }}
		</p>
		<VueEasymde v-else
			:key="card.id"
			ref="markdownEditor"
			v-model="description"
			:configs="mdeConfig"
			@input="updateDescription"
			@blur="saveDescription" />

		<Modal v-if="modalShow" :title="t('deck', 'Choose attachment')" @close="modalShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Choose attachment') }}</h3>
				<AttachmentList
					:card-id="card.id"
					:selectable="true"
					@selectAttachment="addAttachment" />
			</div>
		</Modal>
	</div>
</template>

<script>
import MarkdownIt from 'markdown-it'
import MarkdownItTaskLists from 'markdown-it-task-lists'
import MarkdownItForInline from 'markdown-it-for-inline'
import AttachmentList from './AttachmentList'
import { Actions, ActionButton, Modal } from '@nextcloud/vue'
import { formatFileSize } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { mapState, mapGetters } from 'vuex'

const markdownIt = new MarkdownIt({
	linkify: true,
})
markdownIt.use(MarkdownItTaskLists, { enabled: true, label: true, labelAfter: true })

markdownIt.use(MarkdownItForInline, 'url_new_win', 'link_open', function(tokens, idx) {
	tokens[idx].attrPush(['target', '_blank'])
})

export default {
	name: 'Description',
	components: {
		VueEasymde: () => import('vue-easymde/dist/VueEasyMDE.common'),
		Actions,
		ActionButton,
		Modal,
		AttachmentList,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			description: '',
			markdownIt: null,
			descriptionEditing: false,
			mdeConfig: {
				autoDownloadFontAwesome: false,
				spellChecker: false,
				autofocus: true,
				autosave: { enabled: false, uniqueId: 'unique' },
				toolbar: false,
				placeholder: t('deck', 'Write a description …'),
				previewImagesInEditor: false,
			},
			descriptionSaveTimeout: null,
			descriptionSaving: false,
			descriptionLastEdit: 0,
			modalShow: false,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			cardDetailsInModal: state => state.cardDetailsInModal,
		}),
		...mapGetters(['canEdit']),
		attachments() {
			return [...this.$store.getters.attachmentsByCard(this.id)].sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (mimetype) => {
				const url = OC.MimeType.getIconUrl(mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentUrl() {
			return (attachment) => {
				if (attachment.extendedData.fileid) {
					return generateUrl('/f/' + attachment.extendedData.fileid)
				}
				return generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
			}
		},
		attachmentPreview() {
			return (attachment) => (attachment.extendedData.fileid
				? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=600&y=600&a=true`)
				: generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`))
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		renderedDescription() {
			return markdownIt.render(this.card.description || '')
		},
		hasDescription() {
			return this.card?.description?.trim?.() !== ''
		},
	},
	methods: {
		showEditor() {
			if (!this.canEdit) {
				return
			}
			this.descriptionEditing = true
			this.description = this.card.description
		},
		hideEditor() {
			this.descriptionEditing = false
		},
		showAttachmentModal() {
			this.modalShow = true
		},
		addAttachment(attachment) {
			const descString = this.$refs.markdownEditor.easymde.value()
			let embed = ''
			if ((attachment.type === 'file' && attachment.extendedData.hasPreview) || attachment.extendedData.mimetype.includes('image')) {
				embed = '!'
			}
			const attachmentString = embed + '[📎 ' + attachment.data + '](' + this.attachmentPreview(attachment) + ')'
			const newContent = descString + '\n' + attachmentString
			this.$refs.markdownEditor.easymde.value(newContent)
			this.description = newContent
			this.modalShow = false
			this.updateDescription()
		},
		clickedPreview(e) {
			if (e.target.getAttribute('type') === 'checkbox') {
				const clickedIndex = [...document.querySelector('#description-preview').querySelectorAll('input')].findIndex((li) => li.id === e.target.id)
				const reg = /\[(X|\s|_|-)\]/ig
				let nth = 0
				const updatedDescription = this.card.description.replace(reg, (match, i, original) => {
					let result = match
					if ('' + nth++ === '' + clickedIndex) {
						if (match.match(/^\[\s\]/i)) {
							result = match.replace(/\[\s\]/i, '[x]')
						}
						if (match.match(/^\[x\]/i)) {
							result = match.replace(/\[x\]/i, '[ ]')
						}
						return result
					}
					return match
				})
				this.$store.dispatch('updateCardDesc', { ...this.card, description: updatedDescription })
			}
		},
		async saveDescription() {
			if (this.descriptionLastEdit === 0 || this.descriptionSaving) {
				return
			}
			this.descriptionSaving = true
			await this.$store.dispatch('updateCardDesc', { ...this.card, description: this.description })
			this.descriptionLastEdit = 0
			this.descriptionSaving = false
			this.$emit('change', this.description)
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async() => {
				await this.saveDescription()
			}, 2500)
		},
	},
}
</script>
<style lang="scss" scoped>

.modal__content {
	width: 25vw;
	min-width: 250px;
	min-height: 120px;
	margin: 20px;
	padding-bottom: 20px;
	display: flex;
	flex-direction: column;

	&::v-deep .attachment-list {
		flex-shrink: 1;
		overflow: scroll;
		max-height: 50vh;
	}
}

.placeholder {
	color: var(--color-text-maxcontrast);
	padding: 2px;
}

#description-preview {
	min-height: 100px;

	&::v-deep {
		@import './../../css/markdown';
	}

	&::v-deep input {
		min-height: auto;
	}

	&::v-deep a {
		text-decoration: underline;
	}
}

h5 {
	border-bottom: 1px solid var(--color-border);
	margin-top: 20px;
	margin-bottom: 5px;
	color: var(--color-text-maxcontrast);

	.icon-info {
		display: inline-block;
		width: 32px;
		height: 16px;
		float: right;
		opacity: .7;
	}

	.icon-attach {
		background-size: 16px;
		float: right;
		margin-top: -14px;
		opacity: .7;
	}

	.icon-toggle, .icon-rename {
		float: right;
		margin-top: -14px;
	}
}

</style>
<style>
@import '~easymde/dist/easymde.min.css';

.vue-easymde, .CodeMirror {
	border: none;
	margin: 0;
	padding: 0;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	width: 100%;
}

.CodeMirror-placeholder {
	color: var(--color-text-maxcontrast);
}

.CodeMirror-cursor {
	border-left: 1px solid var(--color-main-text);
}

.editor-preview,
.editor-statusbar {
	display: none;
}

#app-sidebar .app-sidebar-header__desc h4 {
	font-size: 12px !important;
}
</style>
