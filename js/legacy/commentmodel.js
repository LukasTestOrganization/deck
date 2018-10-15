/*
 * Copyright (c) 2016
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

var NS_OWNCLOUD = 'http://owncloud.org/ns';
/**
 * @class CommentModel
 * @classdesc
 *
 * Comment
 *
 */
var CommentModel = OC.Backbone.Model.extend(
	/** @lends OCA.Comments.CommentModel.prototype */ {
	sync: OC.Backbone.davSync,

	/**
	 * Object type
	 *
	 * @type string
	 */
	_objectType: 'deckCard',

	/**
	 * Object id
	 *
	 * @type string
	 */
	_objectId: null,

	initialize: function(model, options) {
		options = options || {};
		if (options.objectType) {
			this._objectType = options.objectType;
		}
		if (options.objectId) {
			this._objectId = options.objectId;
		}
	},

	defaults: {
		actorType: 'users',
		objectType: 'deckCard'
	},

	davProperties: {
		'id': '{' + NS_OWNCLOUD + '}id',
		'message': '{' + NS_OWNCLOUD + '}message',
		'actorType': '{' + NS_OWNCLOUD + '}actorType',
		'actorId': '{' + NS_OWNCLOUD + '}actorId',
		'actorDisplayName': '{' + NS_OWNCLOUD + '}actorDisplayName',
		'creationDateTime': '{' + NS_OWNCLOUD + '}creationDateTime',
		'objectType': '{' + NS_OWNCLOUD + '}objectType',
		'objectId': '{' + NS_OWNCLOUD + '}objectId',
		'isUnread': '{' + NS_OWNCLOUD + '}isUnread',
		'mentions': '{' + NS_OWNCLOUD + '}mentions'
	},

	parse: function(data) {
		return {
			id: data.id,
			message: data.message,
			actorType: data.actorType,
			actorId: data.actorId,
			actorDisplayName: data.actorDisplayName,
			creationDateTime: data.creationDateTime,
			objectType: data.objectType,
			objectId: data.objectId,
			isUnread: (data.isUnread === 'true'),
			mentions: this._parseMentions(data.mentions)
		};
	},

	_parseMentions: function(mentions) {
		if(_.isUndefined(mentions)) {
			return {};
		}
		var result = {};
		for(var i in mentions) {
			var mention = mentions[i];
			if(_.isUndefined(mention.localName) || mention.localName !== 'mention') {
				continue;
			}
			result[i] = {};
			for (var child = mention.firstChild; child; child = child.nextSibling) {
				if(_.isUndefined(child.localName) || !child.localName.startsWith('mention')) {
					continue;
				}
				result[i][child.localName] = child.textContent;
			}
		}
		return result;
	},

	url: function() {
		let baseUrl;
		if (typeof this.collection === 'undefined') {
			baseUrl = OC.linkToRemote('dav') + '/comments/' +
				encodeURIComponent(this.get('objectType')) + '/' +
				encodeURIComponent(this.get('objectId')) + '/';
		} else {
			baseUrl = this.collection.url();
		}
		if (typeof this.get('id') !== 'undefined') {
			return baseUrl + this.get('id');
		} else {
			return baseUrl;
		}
	}
});

export default CommentModel;

