// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Interactions of the FredKI learning space: talk with FredKI, share with the group, react.
 *
 * @module     format_fredki/lernraum
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('format_fredki/lernraum', [
    'core/ajax', 'core/templates', 'core/str', 'core/notification'
], function(Ajax, Templates, Str, Notification) {
    'use strict';

    // Keep in step with lernraum::JS_STRINGS (PHP), which sends these strings with the page.
    var STRINGS = [
        'askround', 'shared', 'thinkfurther', 'thinkfurtherprompt', 'relatedintro', 'fredkipublic',
        'discard', 'fredkierror', 'thinking', 'impulseanswering', 'bothsides', 'viewshared', 'fredkiposted',
        'convenewait', 'conveneempty', 'agentnoanswer', 'more', 'less', 'askcouncilall', 'askcouncilnone',
        'edit', 'editsave', 'editcancel', 'edittitle', 'pendingone', 'pendingmany', 'pendingnow', 'pendingedit',
        'delete', 'deleteconfirm', 'deletewhole', 'definitions_saved', 'definitions_exists', 'definitions_open', 'termai',
        'askprivate', 'askprivateplaceholder', 'agentbusy', 'agentlimit', 'agentpaused', 'agenterror',
        'definitions_count', 'definitions_countone', 'replyfredkiplaceholder', 'whoanswers',
        'topic', 'topic_none', 'topic_new', 'topic_suggest', 'topic_sorting', 'topic_change', 'topic_add', 'topic_all',
        'topic_without', 'topic_create', 'topic_menu'
    ];

    /** @var {Number} Cards on the wall that came from the server pages (for "more conversations"). */
    var offset = 0;

    /** @var {String} Current search text. */
    var query = '';

    /** @var {Number} Search results already shown. */
    var searchoffset = 0;

    /** @var {Number} Chosen topic field: 0 all, -1 without topic field. */
    var topic = 0;

    /** @var {Function|null} Shows the wall or the results for the current search and topic field. */
    var refreshListing = null;

    var config = null;
    var root = null;
    var str = {};

    /**
     * Escape text for HTML.
     *
     * @param {String} text
     * @returns {String}
     */
    var esc = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Light Markdown of FredKI answers to safe HTML: paragraphs, bullet lists, bold.
     *
     * @param {String} text
     * @returns {String}
     */
    var md = function(text) {
        var html = '';
        var list = [];
        var flush = function() {
            if (list.length) {
                html += '<ul><li>' + list.join('</li><li>') + '</li></ul>';
                list = [];
            }
        };
        String(text).replace(/\r/g, '').split(/\n+/).forEach(function(line) {
            line = line.trim();
            if (!line) {
                return;
            }
            var inline = esc(line).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            var bullet = /^(?:[-*•]|\d+[.)])\s+/;
            if (bullet.test(line)) {
                list.push(inline.replace(/^(?:[-*•]|\d+[.)])\s+/, ''));
                return;
            }
            flush();
            html += '<p>' + inline + '</p>';
        });
        flush();
        return html;
    };

    /**
     * Three dots while FredKI writes.
     *
     * @returns {HTMLElement}
     */
    var typing = function() {
        var dots = document.createElement('span');
        dots.className = 'ffk-typing';
        dots.setAttribute('aria-label', str.thinking);
        for (var i = 0; i < 3; i++) {
            dots.appendChild(document.createElement('i'));
        }
        return dots;
    };

    /**
     * A small FredKI orb.
     *
     * @returns {HTMLElement}
     */
    var orb = function() {
        var span = document.createElement('span');
        span.className = 'ffk-orb ffk-orb--xs';
        span.setAttribute('aria-hidden', 'true');
        return span;
    };

    /**
     * Fill an element with a template of the format.
     *
     * @param {HTMLElement} node
     * @param {String} name Template name without component.
     * @param {Object} context
     * @returns {Promise} Resolves with the node.
     */
    var fill = function(node, name, context) {
        return Templates.renderForPromise('format_fredki/' + name, context).then(function(rendered) {
            Templates.replaceNodeContents(node, rendered.html, rendered.js);
            return node;
        });
    };

    /**
     * Add a template of the format at the end of an element.
     *
     * @param {HTMLElement} parent
     * @param {String} name Template name without component.
     * @param {Object} context
     * @returns {Promise} Resolves with the new element.
     */
    var appendTemplate = function(parent, name, context) {
        return Templates.renderForPromise('format_fredki/' + name, context).then(function(rendered) {
            Templates.appendNodeContents(parent, rendered.html, rendered.js);
            return parent.lastElementChild;
        });
    };

    /**
     * Stream an answer of FredKI.
     *
     * @param {Object} params mode, message, discussionid
     * @param {Function} onchunk Called with the full text so far.
     * @returns {Promise} Resolves with the full answer.
     */
    var stream = function(params, onchunk) {
        var body = new URLSearchParams({
            sesskey: config.sesskey,
            courseid: config.courseid,
            section: config.section,
            mode: params.mode,
            message: params.message || '',
            discussionid: params.discussionid || 0,
            threadid: params.threadid || 'main'
        });
        return fetch(config.askurl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {'Accept': 'text/event-stream'}
        }).then(function(response) {
            if (!response.ok || !response.body) {
                throw new Error(str.fredkierror);
            }
            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var full = '';
            var done = {};
            var read = function() {
                return reader.read().then(function(result) {
                    if (result.done) {
                        return {text: full, done: done};
                    }
                    buffer += decoder.decode(result.value, {stream: true});
                    var events = buffer.split('\n\n');
                    buffer = events.pop();
                    for (var i = 0; i < events.length; i++) {
                        var line = events[i].trim();
                        if (line.indexOf('data:') !== 0) {
                            continue;
                        }
                        var event;
                        try {
                            event = JSON.parse(line.slice(5));
                        } catch (e) {
                            continue;
                        }
                        if (event.type === 'chunk') {
                            full += event.content;
                            onchunk(full);
                        } else if (event.type === 'done') {
                            done = event;
                        } else if (event.type === 'error') {
                            throw new Error(event.error || str.fredkierror);
                        }
                    }
                    return read();
                });
            };
            return read();
        });
    };

    /**
     * Prepend a card to the wall, after the impulse card.
     *
     * @param {Object} card Card data from the server.
     * @returns {Promise}
     */
    var addCard = function(card, forumid) {
        // The wall of that forum: the course conversations, or the conversation of a learning step.
        var wall = forumid ? root.querySelector('[data-region="wall"][data-forumid="' + forumid + '"]')
            : root.querySelector('[data-region="wall"]:not([data-forumid])');
        card.hasfredki = config.hasfredki;
        if (!wall) {
            // Not shown on this page (a shared answer on a learning step goes to the course conversations): the card
            // still carries the conversation, so FredKI can answer there.
            return Templates.renderForPromise('format_fredki/card', card).then(function(result) {
                var holder = document.createElement('div');
                holder.innerHTML = result.html;
                return holder.firstElementChild;
            });
        }
        return Templates.renderForPromise('format_fredki/card', card).then(function(result) {
            var holder = document.createElement('div');
            holder.innerHTML = result.html;
            var element = holder.firstElementChild;
            // Just shared: stays visible on top, also while the topic fields are shown.
            element.classList.add('is-fresh');
            var nocards = root.querySelector('[data-region="nocards"]');
            if (nocards) {
                nocards.remove();
            }
            var impulse = wall.querySelector('[data-region="impulse"]');
            if (impulse && impulse.nextSibling) {
                wall.insertBefore(element, impulse.nextSibling);
            } else if (impulse) {
                wall.appendChild(element);
            } else {
                wall.insertBefore(element, wall.firstChild);
            }
            Templates.runTemplateJS(result.js);
            var empty = root.querySelector('.ffk-empty');
            if (empty && empty.parentNode === wall.parentNode) {
                empty.remove();
            }
            return element;
        });
    };

    var createPost = function(args) {
        return Ajax.call([{
            methodname: 'format_fredki_create_post',
            args: {
                courseid: config.courseid,
                forumid: args.forumid || 0,
                text: args.text || '',
                question: args.question || '',
                answer: args.answer || '',
                type: args.type || ''
            }
        }])[0].then(function(response) {
            return addCard(response.card, args.forumid || 0);
        });
    };

    /**
     * Add a FredKI note to a card while FredKI writes.
     *
     * @param {HTMLElement} card
     * @returns {HTMLElement}
     */
    var typingNote = function(card) {
        var notes = card.querySelector('[data-region="notes"]');
        var note = document.createElement('div');
        note.className = 'ffk-note ffk-note--ai';
        var text = document.createElement('span');
        text.className = 'ffk-note__text';
        text.appendChild(typing());
        note.appendChild(orb());
        note.appendChild(text);
        notes.appendChild(note);
        trimNotes(card);
        return note;
    };

    /**
     * FredKI replies publicly in a conversation, as a participant.
     *
     * @param {HTMLElement} card
     * @param {Object} args mode ("mention" or "share"), postid, token
     * @param {HTMLElement} note Existing note to replace, optional.
     * @returns {Promise}
     */
    var fredkiReply = function(card, args, note) {
        note = note || typingNote(card);
        return Ajax.call([{
            methodname: 'format_fredki_fredki_reply',
            args: {
                courseid: config.courseid,
                discussionid: parseInt(card.dataset.discussionid, 10),
                mode: args.mode,
                postid: args.postid || 0,
                token: args.token || ''
            }
        }])[0].then(function(result) {
            if (!result.posted) {
                note.remove();
                return null;
            }
            return Templates.renderForPromise('format_fredki/reply', result.reply).then(function(rendered) {
                var holder = document.createElement('div');
                holder.innerHTML = rendered.html;
                note.replaceWith(holder.firstElementChild);
                bumpReplies(card);
                return result;
            });
        }).catch(function(error) {
            note.remove();
            Notification.exception(error);
        });
    };

    /**
     * Show conversations of the group that relate to a question.
     *
     * @param {String} question
     * @param {HTMLElement} bubble
     */
    var showRelated = function(question, bubble) {
        Ajax.call([{
            methodname: 'format_fredki_related',
            args: {courseid: config.courseid, text: question}
        }])[0].then(function(items) {
            if (!items.length) {
                return;
            }
            return appendTemplate(bubble, 'related', {
                intro: str.relatedintro,
                items: items.map(function(item) {
                    return {url: item.url, label: item.firstname + ': ' + item.subject};
                })
            });
        }).catch(function() {
            // Related conversations are a quiet extra. Errors are not shown.
        });
    };

    /**
     * Private conversation with FredKI in the glass bar.
     *
     * @param {String} question Shown as the learner's bubble.
     * @param {String} mode Mode of the request.
     */
    var ask = function(question, mode) {
        var convo = root.querySelector('[data-region="convo"]');
        if (!convo) {
            return;
        }
        var me = document.createElement('div');
        me.className = 'ffk-bubble ffk-bubble--me';
        me.textContent = question;
        convo.appendChild(me);
        var bubble = document.createElement('div');
        bubble.className = 'ffk-bubble ffk-bubble--ai';
        bubble.replaceChildren(typing());
        convo.appendChild(bubble);
        convo.scrollTop = convo.scrollHeight;

        stream({mode: mode, message: question}, function(text) {
            bubble.innerHTML = md(text);
            convo.scrollTop = convo.scrollHeight;
        }).then(function(result) {
            var answer = result.text;
            var token = result.done.token || '';
            var row = document.createElement('div');
            row.className = 'ffk-acts-row';
            var canshare = !!root.querySelector('[data-region="composer"]');
            // A request for an exercise, a question to me or to go further is personal learning: as a conversation
            // for everyone it would only read "Ask me a question". Only own questions can be shared.
            var personal = question === str.thinkfurtherprompt
                || Array.prototype.some.call(root.querySelectorAll('[data-action="starter"][data-personal="1"]'),
                    function(starter) {
                        return question.indexOf(starter.textContent.trim()) === 0;
                    });
            if (canshare && answer && token && mode === 'ask' && !personal) {
                var share = document.createElement('button');
                share.type = 'button';
                share.className = 'ffk-pillbtn';
                share.textContent = str.askround;
                share.addEventListener('click', function() {
                    share.disabled = true;
                    // Like on X: the question goes to the group and FredKI answers there in public.
                    createPost({text: question, type: 'question'}).then(function(card) {
                        if (card) {
                            fredkiReply(card, {mode: 'share', token: token});
                        }
                        share.textContent = str.shared;
                        if (card) {
                            var view = document.createElement('a');
                            view.className = 'ffk-pillbtn ffk-pillbtn--ghost';
                            view.href = '#runde';
                            view.textContent = str.viewshared;
                            view.addEventListener('click', function(e) {
                                e.preventDefault();
                                card.scrollIntoView({behavior: 'smooth', block: 'center'});
                            });
                            row.appendChild(view);
                        }
                        return card;
                    }).catch(function(error) {
                        share.disabled = false;
                        Notification.exception(error);
                    });
                });
                row.appendChild(share);
            }
            if (mode === 'ask') {
                var further = document.createElement('button');
                further.type = 'button';
                further.className = 'ffk-pillbtn ffk-pillbtn--ghost';
                further.textContent = str.thinkfurther;
                further.addEventListener('click', function() {
                    ask(str.thinkfurtherprompt, 'ask');
                });
                row.appendChild(further);
            }
            bubble.appendChild(row);
            if (result.done.term) {
                appendTemplate(bubble, 'defnote', {
                    text: (result.done.termsaved ? str.definitions_saved : str.definitions_exists).replace('{$a}', result.done.term)
                }).catch(Notification.exception);
                addTerm(result.done);
            } else if (mode === 'ask') {
                showRelated(question, bubble);
            }
            convo.scrollTop = convo.scrollHeight;
            return answer;
        }).catch(function(error) {
            bubble.textContent = error.message || str.fredkierror;
        });
    };

    /**
     * FredKI adds a perspective to a card; the learner decides whether it goes into the group.
     *
     * @param {HTMLElement} card
     * @param {String} mode "perspective" or "contrast"
     */
    var perspective = function(card, mode) {
        var note = typingNote(card);
        var text = note.querySelector('.ffk-note__text');
        var discussionid = parseInt(card.dataset.discussionid || '0', 10);
        stream({mode: mode, discussionid: discussionid}, function(answer) {
            text.innerHTML = md(answer);
        }).then(function(result) {
            var answer = result.text;
            var token = result.done.token || '';
            if (!answer || !discussionid || !token) {
                return answer;
            }
            var row = document.createElement('div');
            row.className = 'ffk-acts-row';
            var post = document.createElement('button');
            post.type = 'button';
            post.className = 'ffk-pillbtn';
            post.textContent = str.fredkipublic;
            var discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'ffk-pillbtn ffk-pillbtn--ghost';
            discard.textContent = str.discard;
            post.addEventListener('click', function() {
                post.disabled = true;
                row.remove();
                text.replaceChildren(typing());
                fredkiReply(card, {mode: 'share', token: token}, note);
            });
            discard.addEventListener('click', function() {
                note.remove();
            });
            row.appendChild(post);
            row.appendChild(discard);
            text.appendChild(row);
            return answer;
        }).catch(function(error) {
            text.textContent = error.message || str.fredkierror;
        });
    };

    /**
     * Keep cards calm: at most two notes, the newest ones.
     *
     * @param {HTMLElement} card
     */
    var trimNotes = function(card) {
        if (card.dataset.view === 'full') {
            return;
        }
        var notes = card.querySelector('[data-region="notes"]');
        while (notes && notes.children.length > 3) {
            notes.removeChild(notes.firstElementChild);
        }
    };

    var bumpReplies = function(card) {
        var count = card.querySelector('[data-region="replycount"]');
        if (count) {
            count.textContent = String(parseInt(count.textContent || '0', 10) + 1);
        }
    };

    /**
     * Agents of the course mentioned in a text, e.g. "@Expertin".
     *
     * @param {String} text
     * @returns {Array}
     */
    var mentionedAgents = function(text) {
        return (config.agents || []).filter(function(agent) {
            return agent.mentions.some(function(name) {
                var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return new RegExp('@[\\s\\u00A0]?' + escaped + '(?![\\p{L}\\p{N}])', 'iu').test(text);
            });
        });
    };

    /**
     * Show that agents are thinking, then wait for their posts and show the card again with them.
     *
     * @param {HTMLElement} card
     * @param {Array} agents Agents expected to answer.
     */
    var awaitAgents = function(card, agents) {
        if (!agents.length) {
            return;
        }
        var notes = card.querySelector('[data-region="notes"]');
        agents.forEach(function(agent) {
            var note = document.createElement('div');
            note.className = 'ffk-note ffk-note--agent ffk-note--thinking';
            note.dataset.thinking = '1';
            notes.appendChild(note);
            fill(note, 'agentnote', {agents: [agent], text: agent.thinking}).catch(Notification.exception);
        });
        var discussionid = parseInt(card.dataset.discussionid, 10);
        var tries = 0;
        var poll = function() {
            tries++;
            Ajax.call([{
                methodname: 'format_fredki_get_card',
                args: {courseid: config.courseid, discussionid: discussionid}
            }])[0].then(function(response) {
                var data = response.card;
                var arrived = data.replycount >= parseInt(card.dataset.replies || '0', 10) + agents.length;
                if (!arrived && tries < 40) {
                    window.setTimeout(poll, 3000);
                    return null;
                }
                data.hasfredki = config.hasfredki;
                var shape = card.className.match(/ffk-card--(wide|tall|square)/);
                data.shape = shape ? shape[1] : data.shape;
                return Templates.renderForPromise('format_fredki/card', data).then(function(rendered) {
                    var holder = document.createElement('div');
                    holder.innerHTML = rendered.html;
                    var fresh = holder.firstElementChild;
                    card.replaceWith(fresh);
                    if (!arrived) {
                        // No answer in time (e.g. the daily limit of the agent is reached): say so instead of waiting.
                        var note = document.createElement('div');
                        note.className = 'ffk-note ffk-note--agent ffk-note--thinking';
                        fresh.querySelector('[data-region="notes"]').appendChild(note);
                        return fill(note, 'agentnote', {agents: [], text: str.agentnoanswer});
                    }
                    return;
                });
            }).catch(function() {
                if (tries < 40) {
                    window.setTimeout(poll, 3000);
                }
            });
        };
        var current = card.querySelector('[data-region="replycount"]');
        card.dataset.replies = current ? current.textContent.trim() : '0';
        window.setTimeout(poll, 2500);
    };

    /**
     * Ask all active agents for their view on a conversation.
     *
     * @param {HTMLElement} card
     * @param {HTMLElement} button
     */
    var convene = function(card, button) {
        button.disabled = true;
        Ajax.call([{
            methodname: 'block_fredkiteamagents_convene',
            args: {discussionid: parseInt(card.dataset.discussionid, 10)}
        }])[0].then(function(result) {
            button.disabled = false;
            if (!result.count) {
                Notification.addNotification({message: str.conveneempty, type: 'info'});
                return;
            }
            awaitAgents(card, (config.agents || []).filter(function(agent) {
                return agent.active;
            }).slice(0, result.count));
            return;
        }).catch(function(error) {
            button.disabled = false;
            Notification.exception(error);
        });
    };

    /**
     * Pause an agent or bring it back (side bar).
     *
     * @param {HTMLElement} button
     */
    var toggleAgent = function(button) {
        var active = button.getAttribute('aria-pressed') !== 'true';
        var agentid = parseInt(button.dataset.agentid, 10);
        button.disabled = true;
        Ajax.call([{
            methodname: 'block_fredkiteamagents_set_active',
            args: {agentid: agentid, active: active}
        }])[0].then(function(result) {
            button.disabled = false;
            button.setAttribute('aria-pressed', result.active ? 'true' : 'false');
            var seat = button.closest('.ffk-seat');
            seat.querySelectorAll('[data-region="agentpic"], [data-region="agenttext"]').forEach(function(el) {
                el.style.opacity = result.active ? '1' : '.45';
            });
            (config.agents || []).forEach(function(agent) {
                if (agent.id === agentid) {
                    agent.active = result.active;
                }
            });
            return;
        }).catch(function(error) {
            button.disabled = false;
            Notification.exception(error);
        });
    };

    /**
     * The cards of a conversation on the page: the card on the wall and, when open, the whole conversation.
     *
     * @param {Number|String} discussionid
     * @returns {NodeList}
     */
    var instancesOf = function(discussionid) {
        return root.querySelectorAll('[data-region="card"][data-discussionid="' + discussionid + '"]');
    };

    /**
     * Where the person looks right now: the open conversation, otherwise the card on the wall.
     *
     * @param {Number|String} discussionid
     * @returns {HTMLElement|null}
     */
    var visibleInstance = function(discussionid) {
        var dialog = root.querySelector('[data-region="convdialog"]');
        if (dialog && dialog.open) {
            var full = dialog.querySelector('[data-region="card"][data-discussionid="' + discussionid + '"]');
            if (full) {
                return full;
            }
        }
        var shown = null;
        instancesOf(discussionid).forEach(function(element) {
            if (!shown && element.dataset.view !== 'full' && element.offsetParent !== null) {
                shown = element;
            }
        });
        return shown;
    };

    /**
     * Draw a card or the whole conversation again with fresh data from the server.
     *
     * @param {HTMLElement} element
     * @returns {Promise} Resolves with the new element.
     */
    var redraw = function(element) {
        var full = element.dataset.view === 'full';
        return Ajax.call([{
            methodname: full ? 'format_fredki_get_conversation' : 'format_fredki_get_card',
            args: {courseid: config.courseid, discussionid: parseInt(element.dataset.discussionid, 10)}
        }])[0].then(function(response) {
            var data = response.card;
            data.hasfredki = config.hasfredki;
            if (!full && element.closest('[data-region="shelves"]')) {
                data.idsuffix = '-s';
            }
            if (!full) {
                var shape = element.className.match(/ffk-card--(wide|tall|square)/);
                data.shape = shape ? shape[1] : data.shape;
                data.isimpulse = element.classList.contains('ffk-card--impulse') || !!element.querySelector('.ffk-kicker');
            }
            return Templates.renderForPromise(full ? 'format_fredki/conversation' : 'format_fredki/card', data);
        }).then(function(rendered) {
            if (!element.isConnected) {
                return null;
            }
            var holder = document.createElement('div');
            holder.innerHTML = rendered.html;
            var fresh = holder.firstElementChild;
            if (element.classList.contains('is-fresh')) {
                fresh.classList.add('is-fresh');
            }
            element.replaceWith(fresh);
            if (!full) {
                applyFilter();
            }
            drawPending(fresh.dataset.discussionid);
            if (full) {
                preparePicker(fresh);
            }
            return fresh;
        });
    };

    /**
     * Draw all cards of a conversation again.
     *
     * @param {Number|String} discussionid
     * @returns {Promise}
     */
    var refreshDiscussion = function(discussionid) {
        var calls = [];
        instancesOf(discussionid).forEach(function(element) {
            calls.push(redraw(element).catch(function() {
                return null;
            }));
        });
        return Promise.all(calls).then(function(result) {
            keepNotices(discussionid);
            return result;
        });
    };

    /** @var {Object} Notes why an agent did not answer, per conversation; they stay until a new answer arrives. */
    var notices = {};

    /**
     * Number of answers a conversation shows now.
     *
     * @param {String|Number} discussionid
     * @returns {Number}
     */
    var answersOf = function(discussionid) {
        var count = 0;
        instancesOf(discussionid).forEach(function(element) {
            var counter = element.querySelector('[data-region="replycount"]');
            count = Math.max(count, counter ? parseInt(counter.textContent, 10) || 0 : 0);
        });
        return count;
    };

    /**
     * Put the notes about missing answers back after a conversation was drawn again.
     *
     * @param {String|Number} discussionid
     */
    var keepNotices = function(discussionid) {
        var list = notices[discussionid];
        if (!list) {
            return;
        }
        var count = answersOf(discussionid);
        list = list.filter(function(notice) {
            return count <= notice.count;
        });
        notices[discussionid] = list;
        list.forEach(function(notice) {
            agentNote(visibleInstance(discussionid), notice.agent, notice.text);
        });
    };

    /**
     * A note "… is thinking" in a card.
     *
     * @param {HTMLElement} card
     * @param {Object|null} agent The agent, or null for a plain note.
     * @param {String} text
     */
    var agentNote = function(card, agent, text) {
        var notes = card ? card.querySelector('[data-region="notes"]') : null;
        if (!notes) {
            return;
        }
        var note = document.createElement('div');
        note.className = 'ffk-note ffk-note--agent ffk-note--thinking';
        notes.appendChild(note);
        fill(note, 'agentnote', {agents: agent ? [agent] : [], text: text}).then(function() {
            if (card.dataset.view === 'full') {
                note.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }
            return note;
        }).catch(Notification.exception);
    };

    /**
     * Why an agent did not answer, in words for the person.
     *
     * @param {Object|null} agent
     * @param {Object} result Result of block_fredkiteamagents_answer_now.
     * @returns {String}
     */
    var noAnswerText = function(agent, result) {
        var name = agent ? agent.name : '';
        var texts = {busy: str.agentbusy, limit: str.agentlimit, paused: str.agentpaused, error: str.agenterror};
        var text = (texts[result.reason] || str.agentnoanswer).replace('{$a}', name);
        return result.diagnostic ? text + ' (' + result.diagnostic + ')' : text;
    };

    /**
     * Refresh a conversation a few more times, for answers that are still being written.
     *
     * @param {String|Number} discussionid
     * @param {Number} times
     */
    var watchDiscussion = function(discussionid, times) {
        if (times <= 0) {
            return;
        }
        window.setTimeout(function() {
            refreshDiscussion(discussionid).catch(function() {
                return null;
            }).then(function() {
                watchDiscussion(discussionid, times - 1);
                return null;
            });
        }, 20000);
    };

    /**
     * Let agents answer a post now, one after another: each sees the answers before it, and the conversation
     * grows calmly instead of being flooded.
     *
     * @param {HTMLElement} card
     * @param {Number} postid Post to answer.
     * @param {Array} agents Agents that answer, in this order.
     * @returns {Promise}
     */
    var answerNow = function(card, postid, agents) {
        if (!agents.length || !postid) {
            return Promise.resolve();
        }
        var discussionid = card.dataset.discussionid;
        var queue = agents.slice();
        var next = function() {
            var agent = queue.shift();
            if (!agent) {
                return Promise.resolve();
            }
            agentNote(visibleInstance(discussionid) || card, agent, agent.thinking);
            return Ajax.call([{
                methodname: 'block_fredkiteamagents_answer_now',
                args: {postid: postid, agentid: agent.id}
            }])[0].catch(function() {
                // The request itself broke off (e.g. the web server gave up waiting).
                return {answerid: 0, reason: 'error', diagnostic: ''};
            }).then(function(result) {
                return refreshDiscussion(discussionid).then(function() {
                    if (!result.answerid) {
                        var notice = {agent: agent, text: noAnswerText(agent, result), count: answersOf(discussionid)};
                        notices[discussionid] = (notices[discussionid] || []).concat([notice]);
                        agentNote(visibleInstance(discussionid), agent, notice.text);
                        if (result.reason === 'busy' || result.reason === 'error') {
                            // The answer may still be written in the background: look again a few times.
                            watchDiscussion(discussionid, 6);
                        }
                    }
                    return next();
                });
            });
        };
        return next();
    };

    /** @var {HTMLElement|null} The floating card that explains a term. */
    var termpop = null;

    /** @var {Number} Timer that hides the term card. */
    var termhide = 0;

    /** @var {RegExp|null} Matches all known terms. */
    var termregex = null;

    /**
     * Build the pattern of all terms, longest first so "rebound effect" wins over "effect".
     */
    var buildTerms = function() {
        var terms = (config.terms || []).map(function(t) {
            return t.concept;
        }).filter(function(c) {
            return c && c.length > 1;
        }).sort(function(a, b) {
            return b.length - a.length;
        }).map(function(c) {
            return c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        });
        try {
            termregex = terms.length ? new RegExp('(?<![\\p{L}\\p{N}])(' + terms.join('|') + ')(?![\\p{L}\\p{N}])', 'giu') : null;
        } catch (e) {
            termregex = null;
        }
    };

    /**
     * The definition of a term (case-insensitive).
     *
     * @param {String} word
     * @returns {Object|null}
     */
    var findTerm = function(word) {
        var lower = String(word || '').toLowerCase();
        var found = null;
        (config.terms || []).forEach(function(t) {
            if (!found && t.concept.toLowerCase() === lower) {
                found = t;
            }
        });
        return found;
    };

    /**
     * Mark the terms of the group in texts of the learning space, so they can be explained on hover.
     *
     * @param {HTMLElement} scope
     */
    var linkTerms = function(scope) {
        if (!termregex || !scope) {
            return;
        }
        var selector = '.ffk-card__say, [data-region="short"], [data-region="full"], .ffk-bubble--ai, .ffk-conv__title, ' +
            '.ffk-conv__text, .ffk-card__say--big';
        var holders = [];
        if (scope.matches && scope.matches(selector)) {
            holders.push(scope);
        }
        scope.querySelectorAll(selector).forEach(function(el) {
            holders.push(el);
        });
        holders.forEach(function(holder) {
            var walker = document.createTreeWalker(holder, NodeFilter.SHOW_TEXT, {
                acceptNode: function(node) {
                    return node.parentElement.closest('.ffk-term, button, textarea, input, script, style') ?
                        NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
                }
            });
            var nodes = [];
            while (walker.nextNode()) {
                nodes.push(walker.currentNode);
            }
            nodes.forEach(function(node) {
                var text = node.nodeValue;
                termregex.lastIndex = 0;
                if (!termregex.test(text)) {
                    return;
                }
                termregex.lastIndex = 0;
                var frag = document.createDocumentFragment();
                var last = 0;
                text.replace(termregex, function(match, word, offset) {
                    frag.appendChild(document.createTextNode(text.slice(last, offset)));
                    var span = document.createElement('span');
                    span.className = 'ffk-term';
                    span.tabIndex = 0;
                    span.dataset.term = word;
                    span.textContent = match;
                    frag.appendChild(span);
                    last = offset + match.length;
                    return match;
                });
                frag.appendChild(document.createTextNode(text.slice(last)));
                node.parentNode.replaceChild(frag, node);
            });
        });
    };

    /**
     * Show the card with the definition next to a term.
     *
     * @param {HTMLElement} target
     */
    var showTerm = function(target) {
        var term = findTerm(target.dataset.term);
        if (!term) {
            return;
        }
        clearTimeout(termhide);
        if (!termpop) {
            termpop = document.createElement('div');
            termpop.className = 'ffk-termpop';
            termpop.setAttribute('role', 'tooltip');
            termpop.addEventListener('mouseenter', function() {
                clearTimeout(termhide);
            });
            termpop.addEventListener('mouseleave', function() {
                hideTerm();
            });
            termpop.addEventListener('click', function(e) {
                var more = e.target.closest('[data-defterm]');
                if (more && openDefinitions(more.dataset.defterm)) {
                    termpop.classList.remove('is-open');
                    termpop.hidden = true;
                }
            });
            // Moodle scrolls inside the page, not the window: a card fixed to the screen stays at its word.
            document.addEventListener('scroll', function() {
                if (termpop && !termpop.hidden) {
                    termpop.classList.remove('is-open');
                    termpop.hidden = true;
                }
            }, true);
        }
        // In an open window (a conversation) the card must sit inside it, or it is drawn behind the window.
        var host = target.closest('dialog[open]') || document.body;
        if (termpop.parentNode !== host) {
            host.appendChild(termpop);
        }
        termpop.dataset.showing = target.dataset.term;
        fill(termpop, 'termpop', {
            concept: term.concept,
            ai: !!term.ai,
            ailabel: str.termai,
            definition: term.definition,
            morelabel: str.definitions_open
        }).then(function() {
            if (termpop.dataset.showing !== target.dataset.term) {
                // Meanwhile another term was chosen.
                return null;
            }
            termpop.hidden = false;
            termpop.classList.add('is-open');
            var r = target.getBoundingClientRect();
            var width = Math.min(320, window.innerWidth - 24);
            termpop.style.width = width + 'px';
            var left = Math.max(12, Math.min(r.left, window.innerWidth - width - 12));
            var top = r.bottom + 8;
            // Not enough room below: above the term.
            var height = termpop.offsetHeight;
            if (r.bottom + height + 16 > window.innerHeight && r.top > height + 16) {
                top = r.top - height - 8;
            }
            termpop.style.left = left + 'px';
            termpop.style.top = top + 'px';
            // An open window may be the frame of "fixed" (it is moved by a transform): correct by what really happened.
            var got = termpop.getBoundingClientRect();
            if (Math.abs(got.left - left) > 1 || Math.abs(got.top - top) > 1) {
                termpop.style.left = (2 * left - got.left) + 'px';
                termpop.style.top = (2 * top - got.top) + 'px';
            }
            return null;
        }).catch(Notification.exception);
    };

    /**
     * Hide the term card after a short moment (the mouse may move into it).
     */
    var hideTerm = function() {
        clearTimeout(termhide);
        termhide = setTimeout(function() {
            if (termpop) {
                termpop.classList.remove('is-open');
                termpop.hidden = true;
            }
        }, 220);
    };

    /**
     * Terms of the group: chips, hover cards, and a new definition added at once.
     */
    var bindTerms = function() {
        buildTerms();
        linkTerms(root);
        var pending = 0;
        var observer = new MutationObserver(function(records) {
            var added = [];
            records.forEach(function(rec) {
                rec.addedNodes.forEach(function(n) {
                    if (n.nodeType === 1 && !n.classList.contains('ffk-term')) {
                        added.push(n);
                    }
                });
            });
            if (!added.length) {
                return;
            }
            clearTimeout(pending);
            pending = setTimeout(function() {
                observer.disconnect();
                added.forEach(function(n) {
                    if (n.isConnected) {
                        linkTerms(n);
                    }
                });
                observer.observe(root, {childList: true, subtree: true});
            }, 250);
        });
        observer.observe(root, {childList: true, subtree: true});
        var over = function(e) {
            var t = e.target.closest ? e.target.closest('.ffk-term, .ffk-termchip') : null;
            if (t && root.contains(t)) {
                showTerm(t);
            }
        };
        var out = function(e) {
            var t = e.target.closest ? e.target.closest('.ffk-term, .ffk-termchip') : null;
            if (t) {
                hideTerm();
            }
        };
        root.addEventListener('mouseover', over);
        root.addEventListener('focusin', over);
        root.addEventListener('mouseout', out);
        root.addEventListener('focusout', out);
        // Touch: a tap shows the card, a tap elsewhere hides it.
        document.addEventListener('click', function(e) {
            var t = e.target.closest ? e.target.closest('.ffk-term, .ffk-termchip') : null;
            if (t && root.contains(t)) {
                if (t.classList.contains('ffk-termchip')) {
                    e.preventDefault();
                }
                showTerm(t);
            } else if (termpop && !termpop.contains(e.target)) {
                hideTerm();
            }
        });
    };

    /**
     * A definition FredKI just kept for the group: show it as chip and explain it everywhere from now on.
     *
     * @param {Object} done Result of the answer.
     */
    var addTerm = function(done) {
        if (!done.term) {
            return;
        }
        if (!findTerm(done.term)) {
            config.terms = config.terms || [];
            config.terms.unshift({concept: done.term, definition: done.termdefinition || '', url: done.termurl || '', ai: true});
            buildTerms();
            var box = root.querySelector('[data-region="terms"]');
            var chips = root.querySelector('[data-region="termchips"]');
            if (box && chips) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'ffk-termchip is-new';
                chip.dataset.term = done.term;
                chip.textContent = done.term;
                chips.insertBefore(chip, chips.firstChild);
                box.hidden = false;
            }
            linkTerms(root);
        }
    };

    /**
     * First letter of a term for the A–Z index (umlauts sort under their base letter).
     *
     * @param {String} concept
     * @returns {String}
     */
    var letterOf = function(concept) {
        var first = String(concept || '').trim().charAt(0).toLocaleUpperCase();
        var base = first.normalize ? first.normalize('NFD').charAt(0) : first;
        return /[A-Z]/.test(base) ? base : '#';
    };

    /**
     * Draw the list of definitions, grouped by letter and filtered by the search words.
     *
     * @param {HTMLElement} dialog
     * @returns {Promise} Resolves with whether this drawing is the current one.
     */
    var drawDefinitions = function(dialog) {
        var search = dialog.querySelector('[data-region="defsearch"]');
        var words = search ? search.value.trim().toLowerCase() : '';
        var lang = document.documentElement.lang || undefined;
        var terms = (config.terms || []).slice().sort(function(a, b) {
            return a.concept.localeCompare(b.concept, lang, {sensitivity: 'base'});
        }).filter(function(t) {
            return !words || (t.concept + ' ' + t.definition).toLowerCase().indexOf(words) !== -1;
        });
        var groups = [];
        var byletter = {};
        terms.forEach(function(t) {
            var letter = letterOf(t.concept);
            if (!byletter[letter]) {
                byletter[letter] = [];
                groups.push(letter);
            }
            byletter[letter].push(t);
        });
        var context = {
            ailabel: str.termai,
            groups: groups.map(function(letter) {
                return {
                    anchor: letter === '#' ? 'other' : letter,
                    letter: letter,
                    terms: byletter[letter].map(function(t) {
                        return {key: t.concept.toLowerCase(), concept: t.concept, ai: !!t.ai, definition: t.definition};
                    })
                };
            })
        };
        dialog.querySelector('[data-region="defnone"]').hidden = terms.length > 0;
        dialog.querySelector('[data-region="defcount"]').textContent = terms.length === 1
            ? str.definitions_countone : str.definitions_count.replace('{$a}', terms.length);
        var draw = String(parseInt(dialog.dataset.draw || '0', 10) + 1);
        dialog.dataset.draw = draw;
        return Promise.all([
            Templates.renderForPromise('format_fredki/definitions', context),
            Templates.renderForPromise('format_fredki/definitionsaz', context)
        ]).then(function(rendered) {
            if (dialog.dataset.draw !== draw) {
                // Meanwhile the search words changed.
                return false;
            }
            Templates.replaceNodeContents(dialog.querySelector('[data-region="deflist"]'), rendered[0].html, rendered[0].js);
            Templates.replaceNodeContents(dialog.querySelector('[data-region="defaz"]'), rendered[1].html, rendered[1].js);
            return true;
        });
    };

    /**
     * Open the definitions of the group in a calm window, optionally at one term.
     *
     * @param {String} [concept]
     * @returns {Boolean} Whether the window could be opened.
     */
    var openDefinitions = function(concept) {
        var dialog = root.querySelector('[data-region="defdialog"]');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return false;
        }
        if (!dialog.dataset.bound) {
            dialog.dataset.bound = '1';
            dialog.addEventListener('click', function(ev) {
                if (ev.target === dialog) {
                    dialog.close();
                    return;
                }
                var jump = ev.target.closest('[data-defletter]');
                if (jump) {
                    ev.preventDefault();
                    var group = dialog.querySelector('#ffk-def-' + jump.dataset.defletter);
                    if (group) {
                        group.scrollIntoView({block: 'start', behavior: 'smooth'});
                    }
                }
            });
            dialog.querySelector('[data-region="defsearch"]').addEventListener('input', function() {
                drawDefinitions(dialog).catch(Notification.exception);
            });
            dialog.addEventListener('close', function() {
                document.documentElement.classList.remove('ffk-noscroll');
            });
        }
        var search = dialog.querySelector('[data-region="defsearch"]');
        search.value = '';
        if (!dialog.open) {
            dialog.showModal();
            document.documentElement.classList.add('ffk-noscroll');
        }
        drawDefinitions(dialog).then(function(current) {
            if (!current) {
                return;
            }
            var item = concept ? dialog.querySelector('[data-defitem="' + CSS.escape(String(concept).toLowerCase()) + '"]')
                : null;
            if (item) {
                item.classList.add('is-focus');
                item.scrollIntoView({block: 'center'});
            } else {
                search.focus();
            }
            return;
        }).catch(Notification.exception);
        return true;
    };

    /**
     * Let a writing field fit its text: one line when empty, a few more while typing, then it scrolls.
     *
     * @param {HTMLTextAreaElement} field
     */
    var grow = function(field) {
        if (!field || field.tagName !== 'TEXTAREA') {
            return;
        }
        field.style.height = 'auto';
        var max = parseFloat(window.getComputedStyle(field).maxHeight) || 9999;
        var height = Math.min(field.scrollHeight + 2, max);
        field.style.height = height + 'px';
        field.classList.toggle('is-full', field.scrollHeight + 2 > max);
        field.classList.toggle('is-multi', field.value.indexOf('\n') !== -1 || height > 52);
    };

    /**
     * Writing fields: Enter sends, Shift+Enter starts a new line, and the field grows with the text.
     */
    var bindGrow = function() {
        root.addEventListener('input', function(e) {
            if (e.target.classList && e.target.classList.contains('ffk-grow')) {
                grow(e.target);
            }
        });
        root.addEventListener('keydown', function(e) {
            var field = e.target;
            if (e.key !== 'Enter' || e.shiftKey || e.isComposing || !field.classList || !field.classList.contains('ffk-grow')) {
                return;
            }
            e.preventDefault();
            var form = field.closest('form');
            if (form && field.value.trim() !== '') {
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                }
            }
        });
        // Fields emptied after sending shrink back to one line.
        root.addEventListener('submit', function() {
            setTimeout(function() {
                root.querySelectorAll('textarea.ffk-grow').forEach(grow);
            }, 1500);
        });
    };

    /** @var {Object} Answers of agents that wait a moment, so a new post can still be corrected. Keyed by post id. */
    var pending = {};

    /**
     * Minutes and seconds: 1:05.
     *
     * @param {Number} ms
     * @returns {String}
     */
    var clock = function(ms) {
        var secs = Math.max(0, Math.ceil(ms / 1000));
        return Math.floor(secs / 60) + ':' + ('0' + (secs % 60)).slice(-2);
    };

    /**
     * Show the waiting answers of a conversation in its visible cards (again after a card was drawn anew).
     *
     * @param {Number|String} discussionid
     */
    var drawPending = function(discussionid) {
        Object.keys(pending).forEach(function(postid) {
            var wait = pending[postid];
            if (String(wait.discussionid) !== String(discussionid)) {
                return;
            }
            instancesOf(discussionid).forEach(function(element) {
                var notes = element.querySelector('[data-region="notes"]');
                if (!notes || notes.querySelector('[data-pending="' + postid + '"]')) {
                    return;
                }
                var names = wait.agents.map(function(agent) {
                    return agent.name;
                }).join(', ');
                var label = (wait.agents.length > 1 ? str.pendingmany : str.pendingone).replace('{$a}', names);
                var note = document.createElement('div');
                note.className = 'ffk-note ffk-note--agent ffk-note--pending';
                note.dataset.pending = postid;
                notes.appendChild(note);
                fill(note, 'agentnote', {
                    pending: true,
                    postid: postid,
                    agents: wait.agents,
                    text: label,
                    clock: clock(wait.deadline - Date.now()),
                    canedit: Date.now() < wait.editUntil,
                    editlabel: str.pendingedit,
                    nowlabel: str.pendingnow
                }).catch(Notification.exception);
            });
        });
    };

    /**
     * Drop the waiting answers of a post (it was taken back).
     *
     * @param {String|Number} postid
     */
    var forgetPending = function(postid) {
        var wait = pending[postid];
        if (wait) {
            clearInterval(wait.timer);
            delete pending[postid];
        }
        root.querySelectorAll('[data-pending="' + postid + '"]').forEach(function(note) {
            note.remove();
        });
    };

    /**
     * Start the waiting answers of a post now.
     *
     * @param {String|Number} postid
     */
    var releasePending = function(postid) {
        var wait = pending[postid];
        if (!wait) {
            return;
        }
        delete pending[postid];
        clearInterval(wait.timer);
        root.querySelectorAll('[data-pending="' + postid + '"]').forEach(function(note) {
            note.remove();
        });
        var card = visibleInstance(wait.discussionid) || instancesOf(wait.discussionid)[0];
        if (card) {
            answerNow(card, parseInt(postid, 10), wait.agents);
        }
    };

    /**
     * Agents answer a new post after a short wait, so the person can still correct a typo. The server keeps a copy of
     * the order and answers by itself if the page is closed in the meantime.
     *
     * @param {HTMLElement} card
     * @param {Number} postid
     * @param {Array} agents
     */
    var scheduleAnswer = function(card, postid, agents) {
        var delay = parseInt(config.answerdelay || '0', 10) * 1000;
        if (!agents.length || !postid) {
            return;
        }
        if (delay <= 0) {
            answerNow(card, postid, agents);
            return;
        }
        Ajax.call([{
            methodname: 'block_fredkiteamagents_schedule',
            args: {postid: postid, agentids: agents.map(function(agent) {
                return agent.id;
            })}
        }])[0].catch(function() {
            return null;
        });
        var wait = {discussionid: card.dataset.discussionid, agents: agents.slice(), deadline: Date.now() + delay,
            editUntil: Date.now() + delay - 2000};
        wait.timer = setInterval(function() {
            var left = wait.deadline - Date.now();
            if (left <= 0) {
                releasePending(postid);
                return;
            }
            root.querySelectorAll('[data-pending="' + postid + '"] [data-region="clock"]').forEach(function(element) {
                element.textContent = clock(left);
            });
            if (Date.now() >= wait.editUntil) {
                root.querySelectorAll('[data-pending="' + postid + '"] [data-action="pending-edit"]').forEach(function(link) {
                    link.remove();
                });
            }
        }, 1000);
        pending[postid] = wait;
        drawPending(wait.discussionid);
    };

    /**
     * Correct an own post in a small dialog.
     *
     * @param {Number} postid
     */
    var editPost = function(postid) {
        Ajax.call([{
            methodname: 'format_fredki_edit_post',
            args: {courseid: config.courseid, postid: postid, text: ''}
        }])[0].then(function(current) {
            return Promise.all([current, Templates.renderForPromise('format_fredki/editdialog', {
                title: str.edittitle,
                delete: str.delete,
                cancel: str.editcancel,
                save: str.editsave
            })]);
        }).then(function(results) {
            var current = results[0];
            var dialog = document.createElement('dialog');
            dialog.className = 'ffk-editdialog';
            Templates.replaceNodeContents(dialog, results[1].html, results[1].js);
            var area = dialog.querySelector('textarea');
            area.value = current.text;
            (root.querySelector('[data-region="convdialog"][open]') || root).appendChild(dialog);
            dialog.querySelector('button[value="cancel"]').addEventListener('click', function() {
                dialog.close();
            });
            dialog.addEventListener('close', function() {
                dialog.remove();
            });
            var remove = dialog.querySelector('button[value="delete"]');
            remove.addEventListener('click', function() {
                // Ask once inside the dialog, then take the post back.
                if (!remove.classList.contains('is-confirm')) {
                    remove.classList.add('is-confirm');
                    remove.textContent = current.isopening ? str.deletewhole : str.deleteconfirm;
                    return;
                }
                remove.disabled = true;
                Ajax.call([{
                    methodname: 'format_fredki_delete_post',
                    args: {courseid: config.courseid, postid: postid}
                }])[0].then(function(result) {
                    dialog.close();
                    forgetPending(postid);
                    if (result.discussiondeleted) {
                        Object.keys(pending).forEach(function(other) {
                            if (String(pending[other].discussionid) === String(result.discussionid)) {
                                forgetPending(other);
                            }
                        });
                        closeConversation();
                        instancesOf(result.discussionid).forEach(function(element) {
                            element.remove();
                        });
                        return null;
                    }
                    return refreshDiscussion(result.discussionid);
                }).catch(function(error) {
                    remove.disabled = false;
                    Notification.exception(error);
                });
            });
            dialog.querySelector('form').addEventListener('submit', function(e) {
                e.preventDefault();
                var text = area.value.trim();
                if (!text) {
                    return;
                }
                area.disabled = true;
                Ajax.call([{
                    methodname: 'format_fredki_edit_post',
                    args: {courseid: config.courseid, postid: postid, text: text}
                }])[0].then(function(result) {
                    dialog.close();
                    // Corrected: the agents wait a little longer, at least a few seconds, then answer the new words.
                    if (pending[postid]) {
                        pending[postid].deadline = Math.max(pending[postid].deadline, Date.now() + 15000);
                    }
                    return refreshDiscussion(result.discussionid);
                }).catch(function(error) {
                    area.disabled = false;
                    Notification.exception(error);
                });
            });
            dialog.showModal();
            area.focus();
            area.setSelectionRange(area.value.length, area.value.length);
            return dialog;
        }).catch(Notification.exception);
    };

    /**
     * Agents that can be asked right now.
     *
     * @returns {Array}
     */
    var activeAgents = function() {
        return (config.agents || []).filter(function(agent) {
            return agent.active;
        });
    };

    /**
     * A name inside a sentence: "Die Expertin" becomes "die Expertin".
     *
     * @param {String} name
     * @returns {String}
     */
    var inSentence = function(name) {
        return name.replace(/^(Die|Der|Das) /, function(article) {
            return article.toLowerCase();
        });
    };

    /**
     * Agents chosen in a selection of agents.
     *
     * @param {HTMLElement} pick
     * @returns {Array}
     */
    var picked = function(pick) {
        if (!pick || pick.hidden) {
            return [];
        }
        var ids = [];
        pick.querySelectorAll('.ffk-pick[aria-pressed="true"]').forEach(function(chip) {
            ids.push(chip.dataset.agentid);
        });
        return activeAgents().filter(function(agent) {
            return ids.indexOf(String(agent.id)) !== -1;
        });
    };

    /**
     * Placeholder of the question field, following the choice of agents.
     *
     * @param {HTMLElement} pick
     * @param {HTMLInputElement} input
     * @param {String} single String key for one agent.
     */
    var pickPlaceholder = function(pick, input, single) {
        if (pick.querySelector('.ffk-pick--private[aria-pressed="true"]')) {
            input.placeholder = str.askprivateplaceholder;
            return;
        }
        if (pick.querySelector('.ffk-pick--fredki[aria-pressed="true"]')) {
            input.placeholder = str.replyfredkiplaceholder;
            return;
        }
        var chosen = picked(pick);
        if (!chosen.length && pick.dataset.optional === '1') {
            // In an answer field the choice is optional: without it, the answer goes to the people only.
            input.placeholder = input.dataset.placeholder || input.placeholder;
            return;
        }
        if (chosen.length === 1) {
            Str.get_string(single, 'format_fredki', inSentence(chosen[0].name)).then(function(text) {
                input.placeholder = text;
                return;
            }).catch(function() {
                input.placeholder = '…';
            });
        } else {
            input.placeholder = chosen.length ? str.askcouncilall : str.askcouncilnone;
        }
    };

    /**
     * The words "Who answers?" in front of the chips.
     *
     * @returns {HTMLElement}
     */
    var pickLabel = function() {
        var label = document.createElement('span');
        label.className = 'ffk-agentpick__label';
        label.textContent = str.whoanswers;
        return label;
    };

    /**
     * Fill a selection of agents: one chip per active agent, the first one chosen. More can be added; they answer
     * one after another, so a new question is not flooded with answers.
     *
     * @param {HTMLElement} pick
     * @param {HTMLInputElement} input
     * @param {String} single String key for the placeholder with one agent.
     */
    var fillPicker = function(pick, input, single, withprivate, optional) {
        var agents = activeAgents();
        pick.replaceChildren();
        pick.dataset.optional = optional ? '1' : '0';
        if (optional) {
            pick.prepend(pickLabel());
        }
        var bar = pick.closest('[data-region="councilbar"]');
        var setPrivate = function(on) {
            // FredKI · privat and the agents of the round exclude each other.
            pick.classList.toggle('is-private', on);
            if (bar) {
                bar.querySelectorAll('[data-region="privatechips"], [data-region="privatehint"]').forEach(function(el) {
                    el.hidden = !on;
                });
            }
        };
        if (withprivate) {
            var mine = document.createElement('button');
            mine.type = 'button';
            mine.className = 'ffk-pick ffk-pick--private';
            mine.dataset.private = '1';
            mine.setAttribute('aria-pressed', 'true');
            fill(mine, 'pickchip', {orb: true, label: str.askprivate}).catch(Notification.exception);
            mine.addEventListener('click', function() {
                var on = mine.getAttribute('aria-pressed') !== 'true';
                mine.setAttribute('aria-pressed', on ? 'true' : 'false');
                if (on) {
                    pick.querySelectorAll('.ffk-pick:not(.ffk-pick--private)').forEach(function(chip) {
                        chip.setAttribute('aria-pressed', 'false');
                    });
                }
                setPrivate(on);
                pickPlaceholder(pick, input, single);
            });
            pick.appendChild(mine);
        }
        agents.forEach(function(agent) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ffk-pick';
            chip.dataset.agentid = String(agent.id);
            chip.setAttribute('aria-pressed', !withprivate && !optional && agents.indexOf(agent) === 0 ? 'true' : 'false');
            chip.style.setProperty('--agent', agent.colour || '#3149d6');
            fill(chip, 'pickchip', {image: agent.image || '', label: agent.name}).catch(Notification.exception);
            chip.addEventListener('click', function() {
                var on = chip.getAttribute('aria-pressed') !== 'true';
                chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                var mineChip = pick.querySelector('.ffk-pick--private');
                if (on && mineChip) {
                    mineChip.setAttribute('aria-pressed', 'false');
                    setPrivate(false);
                }
                pickPlaceholder(pick, input, single);
            });
            pick.appendChild(chip);
        });
        pick.hidden = !agents.length && !withprivate;
        setPrivate(!!withprivate);
        pickPlaceholder(pick, input, single);
    };

    /**
     * Open the reply form of a card, with or without the choice of agents.
     *
     * @param {HTMLElement} card
     * @param {HTMLElement} button
     * @param {Boolean} withagent
     */
    var openReply = function(card, button, withagent) {
        var form = card.querySelector('[data-region="replyform"]');
        if (!form) {
            return;
        }
        var input = form.querySelector('.ffk-grow');
        if (!form.hidden && card.dataset.view !== 'full') {
            form.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            return;
        }
        form.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        preparePicker(card);
        var pick = form.querySelector('[data-region="agentpick"]');
        if (withagent && pick && !pick.hidden) {
            // ✦: start with the choice of who answers; the question itself is optional.
            pick.classList.add('is-hint');
            var first = pick.querySelector('.ffk-pick');
            if (first) {
                first.focus();
                return;
            }
        }
        input.focus();
    };

    /**
     * The same choice everywhere: who should answer (agents, or FredKI in courses without agents), then the text,
     * then send. Nothing is chosen at first; without a choice, an answer goes to the people only.
     *
     * @param {HTMLElement} card
     */
    /**
     * Mode of a question: a taken-over suggestion keeps its own mode (e.g. the summary of the group).
     *
     * @param {HTMLElement} input
     * @param {String} text
     * @returns {String}
     */
    var askMode = function(input, text) {
        var mode = text === input.dataset.starter ? input.dataset.mode || 'ask' : 'ask';
        delete input.dataset.starter;
        delete input.dataset.mode;
        return mode;
    };

    var preparePicker = function(card) {
        var form = card.querySelector('[data-region="replyform"]');
        var pick = form ? form.querySelector('[data-region="agentpick"]') : null;
        if (!pick) {
            return;
        }
        var input = form.querySelector('.ffk-grow');
        if (!input.dataset.placeholder) {
            input.dataset.placeholder = input.placeholder;
        }
        pick.classList.remove('is-hint');
        var withfredki = config.hasfredki && card.dataset.canfredki === '1';
        if (activeAgents().length || withfredki) {
            if (activeAgents().length) {
                fillPicker(pick, input, 'replyagentplaceholder', false, true);
            } else {
                pick.replaceChildren(pickLabel());
                pick.dataset.optional = '1';
            }
            if (!withfredki) {
                return;
            }
            // FredKI can always be asked, also next to the agents of the team.
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'ffk-pick ffk-pick--fredki';
            chip.setAttribute('aria-pressed', 'false');
            fill(chip, 'pickchip', {orb: true, label: 'FredKI'}).catch(Notification.exception);
            chip.addEventListener('click', function() {
                chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                pickPlaceholder(pick, input, 'replyagentplaceholder');
            });
            var label = pick.querySelector('.ffk-agentpick__label');
            pick.insertBefore(chip, label ? label.nextSibling : pick.firstChild);
            pick.hidden = false;
        } else {
            pick.hidden = true;
            pick.replaceChildren();
        }
    };

    /**
     * The question to the expert round on the image: a new conversation for everyone, answered by the chosen agents.
     */
    var bindCouncil = function() {
        var form = root.querySelector('[data-region="councilform"]');
        if (!form) {
            return;
        }
        var bar = form.closest('[data-region="councilbar"]');
        var pick = bar.querySelector('[data-region="agentpick"]');
        var input = form.querySelector('.ffk-grow');
        fillPicker(pick, input, 'askagentplaceholder', !!config.hasfredki);
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var text = input.value.trim();
            if (text && pick.querySelector('.ffk-pick--private[aria-pressed="true"]')) {
                // Only for me: the learning companion answers right here, nothing goes into the round.
                var privatemode = askMode(input, text);
                input.value = '';
                grow(input);
                ask(text, privatemode);
                return;
            }
            var chosen = picked(pick);
            mentionedAgents(text).forEach(function(agent) {
                if (agent.active && chosen.indexOf(agent) === -1) {
                    chosen.push(agent);
                }
            });
            if (!text) {
                return;
            }
            if (!chosen.length) {
                input.placeholder = str.askcouncilnone;
                return;
            }
            input.disabled = true;
            createPost({text: text, type: 'question'}).then(function(card) {
                input.value = '';
                grow(input);
                input.disabled = false;
                if (card) {
                    card.scrollIntoView({behavior: 'smooth', block: 'center'});
                    scheduleAnswer(card, parseInt(card.dataset.postid, 10), chosen);
                }
                return card;
            }).catch(function(error) {
                input.disabled = false;
                Notification.exception(error);
            });
        });
    };

    /**
     * Show the cards of the chosen kind.
     */
    var applyFilter = function() {
        var active = root.querySelector('[data-action="filter"].is-active');
        var filter = active ? active.dataset.filter : 'all';
        root.querySelectorAll('[data-region="wall"] > .ffk-card, [data-region="results"] > .ffk-card,'
            + ' [data-region="shelves"] [data-region="tile"]').forEach(function(item) {
            var show = filter === 'all'
                || (filter === 'fredki' ? item.dataset.byfredki === '1' : item.dataset.type === filter);
            item.hidden = !show;
        });
    };

    /**
     * Put cards from the server on the wall (or the search results), skipping those already there.
     *
     * @param {Array} cards
     * @returns {Promise}
     */
    var appendCards = function(cards) {
        var wall = query || topic ? root.querySelector('[data-region="results"]') : root.querySelector('[data-region="wall"]');
        var renders = cards.map(function(data) {
            data.hasfredki = config.hasfredki;
            return Templates.renderForPromise('format_fredki/card', data);
        });
        return Promise.all(renders).then(function(results) {
            results.forEach(function(rendered, i) {
                if (wall.querySelector('[data-region="card"][data-discussionid="' + cards[i].discussionid + '"]')) {
                    return;
                }
                var holder = document.createElement('div');
                holder.innerHTML = rendered.html;
                wall.appendChild(holder.firstElementChild);
            });
            applyFilter();
            return;
        });
    };

    var loading = false;

    /**
     * Load the next conversations onto the wall (also the next results of a search).
     *
     * @returns {Promise}
     */
    var loadMore = function() {
        var button = root.querySelector('[data-action="loadmore"]');
        if (loading || !button) {
            return Promise.resolve();
        }
        loading = true;
        button.disabled = true;
        var searching = query !== '' || topic !== 0;
        return Ajax.call([{
            methodname: 'format_fredki_get_cards',
            args: {courseid: config.courseid, offset: searching ? searchoffset : offset, query: query, topicid: topic}
        }])[0].then(function(result) {
            if (searching) {
                searchoffset += result.cards.length;
            } else {
                offset += result.cards.length;
            }
            return appendCards(result.cards).then(function() {
                button.hidden = !result.hasmore;
                return;
            });
        }).catch(Notification.exception).then(function() {
            loading = false;
            button.disabled = false;
            return;
        });
    };

    /**
     * Search the conversations; an empty search brings the wall back.
     */
    var bindSearch = function() {
        var form = root.querySelector('[data-region="search"]');
        var wall = root.querySelector('[data-region="wall"]');
        if (!form || !wall) {
            return;
        }
        var input = form.querySelector('input');
        // Results get their own wall; the wall itself stays as it is and comes back when the search is empty.
        var results = document.createElement('div');
        results.className = wall.className;
        results.dataset.region = 'results';
        results.hidden = true;
        wall.parentNode.insertBefore(results, wall.nextSibling);
        var more = root.querySelector('[data-action="loadmore"]');
        var wallmore = more ? more.hidden : true;
        var empty = root.querySelector('[data-region="noresults"]');
        var timer = null;
        var round = root.querySelector('[data-region="round"]');
        var filtered = false;
        refreshListing = function() {
            var now = query !== '' || topic !== 0;
            if (now && !filtered && more) {
                wallmore = more.hidden;
            }
            filtered = now;
            if (round) {
                round.classList.toggle('is-filtered', now);
            }
            if (!now) {
                results.hidden = true;
                results.replaceChildren();
                wall.hidden = false;
                if (more) {
                    more.hidden = wallmore;
                }
                empty.hidden = true;
                return Promise.resolve();
            }
            var text = query;
            var chosen = topic;
            return Ajax.call([{
                methodname: 'format_fredki_get_cards',
                args: {courseid: config.courseid, offset: 0, query: text, topicid: chosen}
            }])[0].then(function(response) {
                if (query !== text || topic !== chosen) {
                    return null;
                }
                results.replaceChildren();
                results.hidden = false;
                wall.hidden = true;
                searchoffset = response.cards.length;
                empty.hidden = response.cards.length > 0;
                if (more) {
                    more.hidden = !response.hasmore;
                }
                return appendCards(response.cards);
            }).catch(Notification.exception);
        };
        var run = function() {
            var text = input.value.trim();
            if (text === query) {
                return;
            }
            query = text;
            refreshListing();
        };
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            window.clearTimeout(timer);
            run();
        });
        input.addEventListener('input', function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 400);
        });
        if (more && 'IntersectionObserver' in window) {
            // More conversations come by themselves when the end of the wall is reached.
            new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !more.hidden) {
                    loadMore();
                }
            }, {rootMargin: '300px'}).observe(more);
        }
    };

    /**
     * Show the conversations of one topic field (0: all, -1: without topic field).
     *
     * @param {Number} chosen
     */
    var chooseTopic = function(chosen) {
        topic = chosen;
        root.querySelectorAll('[data-region="topicchips"] [data-action="topicfilter"]').forEach(function(chip) {
            var on = parseInt(chip.dataset.topic, 10) === chosen;
            chip.classList.toggle('is-active', on);
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (refreshListing) {
            refreshListing();
        }
        var chips = root.querySelector('[data-region="topicchips"]');
        if (chips && chips.getBoundingClientRect().top < 0) {
            chips.scrollIntoView({block: 'start', behavior: 'smooth'});
        }
    };

    /**
     * Switch between topic fields (shelves), cards and list; the choice is remembered for the person.
     *
     * @param {String} view
     */
    var chooseView = function(view) {
        var round = root.querySelector('[data-region="round"]');
        if (!round) {
            return;
        }
        round.classList.remove('ffk-round--topics', 'ffk-round--cards', 'ffk-round--list');
        round.classList.add('ffk-round--' + view);
        round.dataset.view = view;
        root.querySelectorAll('[data-action="view"]').forEach(function(button) {
            var on = button.dataset.view === view;
            button.classList.toggle('is-active', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (view === 'topics' && topic !== 0) {
            chooseTopic(0);
        }
        Ajax.call([{
            methodname: 'core_user_update_user_preferences',
            args: {preferences: [{type: 'format_fredki_view', value: view}]}
        }])[0].catch(function() {
            return null;
        });
    };

    /**
     * Draw the chips of the topic fields again (after a change of a topic field).
     *
     * @param {Array} topics
     * @param {Number} without Conversations without topic field.
     */
    var drawTopicChips = function(topics, without) {
        config.topics = topics;
        var chips = root.querySelector('[data-region="topicchips"]');
        if (!chips) {
            var filters = root.querySelector('.ffk-filters');
            if (!filters || !topics.length) {
                return;
            }
            chips = document.createElement('div');
            chips.className = 'ffk-topics';
            chips.dataset.region = 'topicchips';
            chips.setAttribute('role', 'group');
            chips.setAttribute('aria-label', str.topic_menu);
            filters.parentNode.insertBefore(chips, filters.nextSibling);
        }
        var chip = function(id, label, count, extra) {
            return {id: id, label: label, count: count, hascount: count !== null, without: !!extra, on: id === topic};
        };
        var list = [chip(0, str.topic_all, null)];
        topics.forEach(function(t) {
            if (t.count > 0 || t.id === topic) {
                list.push(chip(t.id, t.name, t.count));
            }
        });
        if (without > 0) {
            list.push(chip(-1, str.topic_without, without, true));
        }
        fill(chips, 'topicchips', {chips: list}).catch(Notification.exception);
    };

    /**
     * Show the topic field on all cards of a conversation.
     *
     * @param {Number|String} discussionid
     * @param {Number} topicid
     * @param {String} name
     * @param {Boolean} sorting FredKI is still choosing.
     */
    var drawTopicLine = function(discussionid, topicid, name, sorting) {
        instancesOf(discussionid).forEach(function(card) {
            var line = card.querySelector('[data-region="topicline"]');
            if (!line) {
                return;
            }
            var edit = line.querySelector('[data-action="topicmenu"]') || line.topicmenu;
            if (edit) {
                // Kept while the line is drawn again.
                line.topicmenu = edit;
            }
            var draw = String(parseInt(line.dataset.draw || '0', 10) + 1);
            line.dataset.draw = draw;
            card.dataset.topic = topicid || 0;
            fill(line, 'topicline', {
                sorting: !!sorting,
                sortinglabel: str.topic_sorting,
                topicid: topicid || 0,
                name: name || ''
            }).then(function() {
                if (line.dataset.draw !== draw || !edit || sorting) {
                    return line;
                }
                edit.title = topicid ? str.topic_change : str.topic_add;
                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', topicid ? 'M4 20h4L19 9l-4-4L4 16v4z' : 'M12 5v14M5 12h14');
                edit.querySelector('svg').replaceChildren(path);
                var span = edit.querySelector('span');
                span.className = topicid ? 'sr-only' : '';
                span.textContent = topicid ? str.topic_change : str.topic;
                line.appendChild(edit);
                return line;
            }).catch(Notification.exception);
        });
    };

    /**
     * Put a conversation into a topic field.
     *
     * @param {Number|String} discussionid
     * @param {Object} args topicid, name or suggest
     * @returns {Promise}
     */
    var setTopic = function(discussionid, args) {
        var before = instancesOf(discussionid)[0];
        var line = before ? before.querySelector('[data-region="topicline"]') : null;
        var keep = line ? Array.prototype.map.call(line.childNodes, function(node) {
            return node.cloneNode(true);
        }) : [];
        if (args.suggest) {
            drawTopicLine(discussionid, 0, '', true);
        }
        return Ajax.call([{
            methodname: 'format_fredki_set_topic',
            args: {
                courseid: config.courseid,
                discussionid: parseInt(discussionid, 10),
                topicid: args.topicid || 0,
                name: args.name || '',
                suggest: !!args.suggest
            }
        }])[0].then(function(result) {
            drawTopicLine(discussionid, result.topicid, result.topicname, false);
            // Cards without an own picture take the picture of their topic field.
            instancesOf(discussionid).forEach(function(card) {
                var img = card.querySelector('.ffk-card__img');
                var src = img ? img.getAttribute('src') || '' : '';
                if (img && /format_fredki/.test(src) && /photo_|topicimages/.test(src) && result.image) {
                    img.src = result.image;
                    img.setAttribute('style', result.imagestyle || '');
                }
            });
            drawTopicChips(result.topics, result.without);
            return result;
        }).catch(function(error) {
            if (line && line.isConnected) {
                line.dataset.draw = String(parseInt(line.dataset.draw || '0', 10) + 1);
                line.replaceChildren.apply(line, keep);
            }
            if (!args.suggest) {
                Notification.exception(error);
            }
        });
    };

    /**
     * Small menu to choose the topic field of a conversation.
     *
     * @param {HTMLElement} button
     * @param {HTMLElement} card
     */
    var openTopicMenu = function(button, card) {
        var old = document.querySelector('.ffk-topicmenu');
        if (old) {
            old.remove();
            if (old.dataset.discussionid === card.dataset.discussionid) {
                button.setAttribute('aria-expanded', 'false');
                return;
            }
        }
        var current = parseInt(card.dataset.topic || '0', 10);
        if (!card.dataset.topic) {
            var tag = card.querySelector('[data-region="topicline"] .ffk-topictag[data-topic]');
            current = tag ? parseInt(tag.dataset.topic, 10) : 0;
        }
        var menu = document.createElement('div');
        menu.className = 'ffk-topicmenu';
        menu.dataset.discussionid = card.dataset.discussionid;
        menu.setAttribute('role', 'dialog');
        menu.setAttribute('aria-label', str.topic_menu);
        menu.style.visibility = 'hidden';
        document.body.appendChild(menu);
        fill(menu, 'topicmenu', {
            items: (config.topics || []).map(function(t) {
                return {id: t.id, name: t.name, on: t.id === current};
            }),
            current: current,
            nonelabel: str.topic_none,
            createlabel: str.topic_create,
            newlabel: str.topic_new,
            hasfredki: !!config.hasfredki,
            suggestlabel: str.topic_suggest
        }).then(function() {
            if (!menu.isConnected) {
                return null;
            }
            var rect = button.getBoundingClientRect();
            var width = menu.offsetWidth;
            menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.left)) + 'px';
            var below = rect.bottom + 6;
            menu.style.top = (below + menu.offsetHeight > window.innerHeight - 8
                ? Math.max(8, rect.top - menu.offsetHeight - 6) : below) + 'px';
            menu.style.visibility = '';
            var first = menu.querySelector('.is-on') || menu.querySelector('button, input');
            if (first) {
                first.focus();
            }
            return null;
        }).catch(Notification.exception);
        button.setAttribute('aria-expanded', 'true');
        var close = function(refocus) {
            menu.remove();
            button.setAttribute('aria-expanded', 'false');
            document.removeEventListener('mousedown', outside, true);
            document.removeEventListener('keydown', key, true);
            window.removeEventListener('scroll', scrolled, true);
            if (refocus && button.isConnected) {
                button.focus();
            }
        };
        var outside = function(e) {
            if (!menu.contains(e.target) && e.target !== button && !button.contains(e.target)) {
                close(false);
            }
        };
        var key = function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close(true);
            }
        };
        var scrolled = function(e) {
            if (!menu.contains(e.target)) {
                close(false);
            }
        };
        document.addEventListener('mousedown', outside, true);
        document.addEventListener('keydown', key, true);
        window.addEventListener('scroll', scrolled, true);
        menu.addEventListener('click', function(e) {
            var item = e.target.closest('[data-topic], [data-suggest]');
            if (!item) {
                return;
            }
            close(true);
            setTopic(card.dataset.discussionid, item.dataset.suggest ? {suggest: true}
                : {topicid: parseInt(item.dataset.topic, 10)});
        });
        menu.addEventListener('submit', function(e) {
            e.preventDefault();
            var name = menu.querySelector('input').value.trim();
            if (name) {
                close(true);
                setTopic(card.dataset.discussionid, {name: name});
            }
        });
    };

    /**
     * Open a whole conversation on the course page, above the wall.
     *
     * @param {Number|String} discussionid
     */
    var openConversation = function(discussionid) {
        var dialog = root.querySelector('[data-region="convdialog"]');
        if (!dialog) {
            dialog = document.createElement('dialog');
            dialog.className = 'ffk-dialog';
            dialog.dataset.region = 'convdialog';
            root.appendChild(dialog);
            dialog.addEventListener('click', function(e) {
                // A tap on the dim background closes the conversation.
                if (e.target === dialog) {
                    closeConversation();
                }
            });
            dialog.addEventListener('close', function() {
                var open = dialog.dataset.discussionid;
                dialog.replaceChildren();
                delete dialog.dataset.discussionid;
                document.documentElement.classList.remove('ffk-noscroll');
                if (window.location.hash.indexOf('#gespraech-') === 0) {
                    window.history.replaceState(null, '', window.location.pathname + window.location.search);
                }
                if (open) {
                    refreshDiscussion(open);
                }
            });
        }
        dialog.dataset.discussionid = String(discussionid);
        var loading = document.createElement('div');
        loading.className = 'ffk-conv ffk-conv--loading';
        loading.appendChild(typing());
        dialog.replaceChildren(loading);
        if (!dialog.open) {
            dialog.showModal();
        }
        document.documentElement.classList.add('ffk-noscroll');
        window.history.replaceState(null, '', '#gespraech-' + discussionid);
        Ajax.call([{
            methodname: 'format_fredki_get_conversation',
            args: {courseid: config.courseid, discussionid: parseInt(discussionid, 10)}
        }])[0].then(function(response) {
            var data = response.card;
            data.hasfredki = config.hasfredki;
            return Templates.renderForPromise('format_fredki/conversation', data);
        }).then(function(rendered) {
            if (dialog.dataset.discussionid !== String(discussionid)) {
                return;
            }
            dialog.innerHTML = rendered.html;
            Templates.runTemplateJS(rendered.js);
            drawPending(discussionid);
            var convcard = dialog.querySelector('[data-region="card"]');
            if (convcard) {
                preparePicker(convcard);
            }
            var title = dialog.querySelector('.ffk-conv__title');
            if (title) {
                title.setAttribute('tabindex', '-1');
                title.focus();
            }
            return;
        }).catch(function(error) {
            dialog.close();
            Notification.exception(error);
        });
    };

    /**
     * Answer a single post of the conversation, or (postid 0) the conversation as a whole.
     *
     * @param {HTMLElement} card The whole conversation.
     * @param {Number} postid
     * @param {String} name Author of the post.
     * @param {Boolean} isagent Whether an agent wrote it ("Antwort an die Expertin").
     */
    var setReplyTarget = function(card, postid, name, isagent) {
        var form = card.querySelector('[data-region="replyform"]');
        if (!form) {
            return;
        }
        var head = form.querySelector('[data-region="replyhead"]');
        var reset = form.querySelector('[data-action="replyreset"]');
        var input = form.querySelector('.ffk-grow');
        form.dataset.parentid = String(postid || 0);
        card.querySelectorAll('.ffk-note.is-target').forEach(function(note) {
            note.classList.remove('is-target');
        });
        if (postid && name) {
            var who = isagent ? inSentence(name) : name;
            Str.get_string(isagent ? 'replytoagent' : 'replyto', 'format_fredki', who).then(function(text) {
                head.textContent = text;
                return;
            }).catch(Notification.exception);
            reset.hidden = false;
            var target = card.querySelector('[data-action="replyto"][data-postid="' + postid + '"]');
            if (target) {
                target.closest('.ffk-note').classList.add('is-target');
            }
            // Answering FredKI or an agent: that voice is chosen to answer back (can be switched off).
            var pick = form.querySelector('[data-region="agentpick"]');
            if (pick && !pick.hidden) {
                var answerer = null;
                if (!isagent && name === 'FredKI') {
                    answerer = pick.querySelector('.ffk-pick--fredki');
                } else if (isagent) {
                    activeAgents().forEach(function(agent) {
                        if (agent.name === name) {
                            answerer = pick.querySelector('.ffk-pick[data-agentid="' + agent.id + '"]');
                        }
                    });
                }
                if (answerer && answerer.getAttribute('aria-pressed') !== 'true') {
                    answerer.click();
                }
            }
        } else {
            head.textContent = head.dataset.default;
            reset.hidden = true;
        }
        input.focus();
    };

    /**
     * Close the open conversation.
     */
    var closeConversation = function() {
        var dialog = root.querySelector('[data-region="convdialog"]');
        if (dialog && dialog.open) {
            dialog.close();
        }
    };

    var bindWall = function() {
        root.addEventListener('click', function(e) {
            var button = e.target.closest('[data-action]');
            if (!button) {
                // The answer preview on a card opens the whole conversation.
                var preview = e.target.closest('.ffk-card__notes--preview .ffk-note:not(.ffk-note--pending)');
                var owner = preview && preview.closest('[data-region="card"]');
                if (owner && root.contains(owner) && !window.getSelection().toString()) {
                    openConversation(owner.dataset.discussionid);
                }
                return;
            }
            if (!root.contains(button)) {
                return;
            }
            var card = button.closest('[data-region="card"], [data-region="impulse"]');
            var action = button.dataset.action;

            if (action === 'topicfilter') {
                e.preventDefault();
                chooseTopic(parseInt(button.dataset.topic, 10));
                return;
            }
            if (action === 'opentile') {
                e.preventDefault();
                openConversation(button.dataset.discussionid);
                return;
            }
            if (action === 'view') {
                chooseView(button.dataset.view);
                return;
            }
            if (action === 'hideaihint') {
                var hint = button.closest('[data-region="aihint"]');
                if (hint) {
                    hint.remove();
                }
                Ajax.call([{
                    methodname: 'core_user_update_user_preferences',
                    args: {preferences: [{type: 'format_fredki_aihint', value: 1}]}
                }])[0].catch(function() {
                    return null;
                });
                return;
            }
            if (action === 'topicmenu' && card) {
                openTopicMenu(button, card);
                return;
            }
            if (action === 'filter') {
                root.querySelectorAll('[data-action="filter"]').forEach(function(other) {
                    other.classList.toggle('is-active', other === button);
                    other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                });
                applyFilter();
            } else if (action === 'open' && card) {
                e.preventDefault();
                openConversation(card.dataset.discussionid);
            } else if (action === 'edit' || action === 'pending-edit') {
                if (action === 'edit' && parseInt(button.dataset.until || '0', 10) * 1000 < Date.now()) {
                    button.remove();
                    return;
                }
                editPost(parseInt(button.dataset.postid, 10));
            } else if (action === 'pending-now') {
                releasePending(button.dataset.postid);
            } else if (action === 'replyto' && card) {
                setReplyTarget(card, parseInt(button.dataset.postid, 10), button.dataset.name || '', button.dataset.agent === '1');
            } else if (action === 'replyreset' && card) {
                setReplyTarget(card, 0, '');
            } else if (action === 'definitions') {
                if (openDefinitions(button.dataset.term || '')) {
                    e.preventDefault();
                }
            } else if (action === 'privacy') {
                var info = root.querySelector('[data-region="privacydialog"]');
                if (info && !info.open) {
                    info.showModal();
                    if (!info.dataset.bound) {
                        info.dataset.bound = '1';
                        info.addEventListener('click', function(ev) {
                            if (ev.target === info) {
                                info.close();
                            }
                        });
                    }
                }
            } else if (action === 'closeprivacy') {
                button.closest('dialog').close();
            } else if (action === 'closeconv') {
                closeConversation();
            } else if (action === 'nextagent' && card) {
                var nextid = parseInt(button.dataset.agentid, 10);
                var nextagent = activeAgents().filter(function(agent) {
                    return agent.id === nextid;
                })[0];
                if (nextagent) {
                    var row = button.closest('[data-region="nextview"]');
                    row.querySelectorAll('button').forEach(function(other) {
                        other.disabled = true;
                    });
                    answerNow(card, parseInt(card.dataset.lastpost || card.dataset.postid, 10), [nextagent]);
                }
            } else if (action === 'loadmore') {
                loadMore();
            } else if (action === 'starter') {
                // A suggestion fills the field; sending is always the same step (arrow or Enter).
                var startbar = button.closest('[data-region="askbar"], [data-region="councilbar"]');
                var startinput = startbar ? startbar.querySelector('.ffk-grow, input[type="text"]') : null;
                if (startinput) {
                    startinput.value = button.textContent.trim();
                    startinput.dataset.starter = startinput.value;
                    startinput.dataset.mode = button.dataset.mode === 'summary' ? 'summary' : 'ask';
                    if (startinput.classList.contains('ffk-grow')) {
                        grow(startinput);
                    }
                    // After the click has settled, so the field keeps the focus: Enter or the arrow sends.
                    setTimeout(function() {
                        startinput.focus();
                        startinput.setSelectionRange(startinput.value.length, startinput.value.length);
                    }, 0);
                } else {
                    ask(button.textContent.trim(), button.dataset.mode === 'summary' ? 'summary' : 'ask');
                }
            } else if (action === 'like' && card) {
                // A heart belongs to one post: the opening post (card) or a reply (its own button).
                var likepost = parseInt(button.dataset.postid || card.dataset.postid, 10);
                button.disabled = true;
                Ajax.call([{
                    methodname: 'format_fredki_toggle_like',
                    args: {courseid: config.courseid, postid: likepost}
                }])[0].then(function(result) {
                    button.disabled = false;
                    button.classList.toggle('is-on', result.liked);
                    button.setAttribute('aria-pressed', result.liked ? 'true' : 'false');
                    var shown = button.dataset.postid && !result.count ? '' : String(result.count);
                    button.querySelector('[data-region="likecount"]').textContent = shown;
                    return;
                }).catch(function(error) {
                    button.disabled = false;
                    Notification.exception(error);
                });
            } else if (action === 'reply' && card) {
                openReply(card, button, false);
            } else if (action === 'askagent' && card) {
                openReply(card, button, true);
            } else if (action === 'more') {
                var note = button.closest('.ffk-note');
                var full = note.querySelector('[data-region="full"]');
                var short = note.querySelector('[data-region="short"]');
                var open = full.hidden;
                full.hidden = !open;
                short.hidden = open;
                button.textContent = open ? str.less : str.more;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            } else if (action === 'convene' && card) {
                convene(card, button);
            } else if (action === 'agenttoggle') {
                toggleAgent(button);
            } else if (action === 'perspective' && card) {
                openReply(card, button, true);
            } else if (action === 'contrast' && card) {
                perspective(card, 'contrast');
            } else if (action === 'impulse-answer' && card) {
                var composer = root.querySelector('[data-region="composer"]');
                if (composer) {
                    var question = card.querySelector('[data-region="impulsetext"]').textContent.trim();
                    composer.dataset.question = question;
                    composer.classList.add('is-answering');
                    var input = composer.querySelector('.ffk-grow');
                    input.placeholder = str.impulseanswering;
                    composer.scrollIntoView({behavior: 'smooth', block: 'center'});
                    input.focus();
                }
            }
        });

        root.addEventListener('submit', function(e) {
            var form = e.target;
            if (form.dataset.region === 'replyform') {
                e.preventDefault();
                var card = form.closest('[data-region="card"]');
                var input = form.querySelector('.ffk-grow');
                var pick = form.querySelector('[data-region="agentpick"]');
                var chosen = picked(pick);
                var withfredki = !!(pick && !pick.hidden && pick.querySelector('.ffk-pick--fredki[aria-pressed="true"]'));
                var text = input.value.trim();
                var settle = function() {
                    if (card.dataset.view !== 'full') {
                        form.hidden = true;
                    }
                    preparePicker(card);
                    input.placeholder = input.dataset.placeholder || input.placeholder;
                };
                if (!text) {
                    // Nothing written: the chosen voices answer what is there.
                    if (chosen.length) {
                        answerNow(card, parseInt(card.dataset.lastpost || card.dataset.postid, 10), chosen);
                        settle();
                    } else if (withfredki) {
                        perspective(card, 'perspective');
                        settle();
                    } else {
                        input.focus();
                    }
                    return;
                }
                input.disabled = true;
                Ajax.call([{
                    methodname: 'format_fredki_add_reply',
                    args: {
                        courseid: config.courseid,
                        discussionid: parseInt(card.dataset.discussionid, 10),
                        text: text,
                        isfredki: false,
                        parentid: parseInt(form.dataset.parentid || '0', 10)
                    }
                }])[0].then(function(result) {
                    return Templates.renderForPromise('format_fredki/reply', result.reply).then(function(rendered) {
                        var holder = document.createElement('div');
                        holder.innerHTML = rendered.html;
                        card.querySelector('[data-region="notes"]').appendChild(holder.firstElementChild);
                        trimNotes(card);
                        bumpReplies(card);
                        input.value = '';
                        grow(input);
                        input.disabled = false;
                        card.dataset.lastpost = String(result.postid);
                        if (card.dataset.view !== 'full') {
                            form.hidden = true;
                        }
                        form.dataset.withagent = '0';
                        if (card.dataset.view === 'full') {
                            setReplyTarget(card, 0, '');
                            input.blur();
                        }
                        preparePicker(card);
                        input.placeholder = input.dataset.placeholder || input.placeholder;
                        if (withfredki || (config.hasfredki && /@fredki\b/i.test(text))) {
                            fredkiReply(card, {mode: 'mention', postid: result.postid});
                        }
                        var targets = chosen.slice();
                        mentionedAgents(text).forEach(function(agent) {
                            if (agent.active && targets.indexOf(agent) === -1) {
                                targets.push(agent);
                            }
                        });
                        scheduleAnswer(card, result.postid, targets);
                        return;
                    });
                }).catch(function(error) {
                    input.disabled = false;
                    Notification.exception(error);
                });
            } else if (form.dataset.region === 'composer') {
                e.preventDefault();
                var field = form.querySelector('.ffk-grow');
                var value = field.value.trim();
                if (!value) {
                    return;
                }
                field.disabled = true;
                var kind = form.querySelector('input[name="ffk-kind"]:checked');
                createPost({
                    forumid: parseInt(form.dataset.forumid || '0', 10),
                    text: value,
                    question: form.dataset.question || '',
                    type: kind ? kind.value : ''
                }).then(function(card) {
                    field.value = '';
                    grow(field);
                    field.disabled = false;
                    delete form.dataset.question;
                    form.classList.remove('is-answering');
                    field.placeholder = field.getAttribute('data-placeholder') || field.placeholder;
                    if (card && /@fredki\b/i.test(value) && config.hasfredki) {
                        fredkiReply(card, {mode: 'mention', postid: parseInt(card.dataset.postid, 10)});
                    }
                    if (card && config.hasfredki) {
                        setTopic(card.dataset.discussionid, {suggest: true});
                    }
                    if (card) {
                        scheduleAnswer(card, parseInt(card.dataset.postid, 10), mentionedAgents(value).filter(function(agent) {
                            return agent.active;
                        }));
                    }
                    return card;
                }).catch(function(error) {
                    field.disabled = false;
                    Notification.exception(error);
                });
            } else if (form.dataset.region === 'askform') {
                e.preventDefault();
                var askinput = form.querySelector('.ffk-grow');
                var question = askinput.value.trim();
                if (question) {
                    var askmode = askMode(askinput, question);
                    askinput.value = '';
                    grow(askinput);
                    ask(question, askmode);
                }
            }
        });
    };

    /**
     * People at the top: one card open at a time, closed by a tap elsewhere or Escape.
     */
    var bindPeople = function() {
        var all = function() {
            return root.querySelectorAll('[data-region="person"]');
        };
        root.addEventListener('toggle', function(e) {
            var opened = e.target;
            if (opened.dataset && opened.dataset.region === 'person' && opened.open) {
                all().forEach(function(other) {
                    if (other !== opened) {
                        other.open = false;
                    }
                });
            }
        }, true);
        document.addEventListener('click', function(e) {
            all().forEach(function(person) {
                if (person.open && !person.contains(e.target)) {
                    person.open = false;
                }
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') {
                return;
            }
            all().forEach(function(person) {
                if (person.open) {
                    person.open = false;
                    person.querySelector('summary').focus();
                }
            });
        });
    };

    var loadImpulse = function() {
        var card = root.querySelector('[data-region="impulse"]');
        if (!card || !config.impulsepending) {
            return;
        }
        var target = card.querySelector('[data-region="impulsetext"]');
        // The raw answer is not shown: some agents write long texts. The server returns one short question.
        stream({mode: 'impulse'}, function() {
            return;
        }).then(function(result) {
            if (result.done.question) {
                target.textContent = result.done.question;
            }
            var discussionid = parseInt(result.done.discussionid || '0', 10);
            if (!discussionid) {
                return null;
            }
            // FredKI asked the question in the forum: show it as a real conversation of the group.
            return Ajax.call([{
                methodname: 'format_fredki_get_card',
                args: {courseid: config.courseid, discussionid: discussionid}
            }])[0].then(function(response) {
                var data = response.card;
                data.hasfredki = config.hasfredki;
                data.isimpulse = true;
                data.shape = 'tall';
                return Templates.renderForPromise('format_fredki/card', data);
            }).then(function(rendered) {
                var holder = document.createElement('div');
                holder.innerHTML = rendered.html;
                card.replaceWith(holder.firstElementChild);
                return discussionid;
            });
        }).catch(function() {
            card.remove();
        });
    };

    return {
        /**
         * Initialise the learning space.
         *
         * @param {Object} cfg Configuration from the server.
         */
        init: function(cfg) {
            config = cfg;
            root = document.querySelector('[data-region="format-fredki"]');
            if (!root) {
                return;
            }
            var composer = root.querySelector('[data-region="composer"] .ffk-grow');
            if (composer) {
                composer.setAttribute('data-placeholder', composer.placeholder);
            }
            Str.get_strings(STRINGS.map(function(key) {
                return {key: key, component: 'format_fredki'};
            })).then(function(values) {
                STRINGS.forEach(function(key, i) {
                    str[key] = values[i];
                });
                var wall = root.querySelector('[data-region="wall"]');
                offset = wall ? parseInt(wall.dataset.offset || '0', 10) : 0;
                bindWall();
                bindGrow();
                bindTerms();
                // Own posts can be corrected only for a short time after writing: the links disappear by themselves.
                setInterval(function() {
                    root.querySelectorAll('[data-action="edit"][data-until]').forEach(function(link) {
                        if (parseInt(link.dataset.until || '0', 10) * 1000 < Date.now()) {
                            link.remove();
                        }
                    });
                }, 10000);
                bindCouncil();
                bindPeople();
                bindSearch();
                var hash = window.location.hash.match(/^#gespraech-(\d+)$/);
                if (hash) {
                    openConversation(hash[1]);
                }
                // Coming from a learning step: show the conversations of one topic field (-1: without one).
                var topichash = window.location.hash.match(/^#thema-(-?\d+)$/);
                if (topichash && root.querySelector('[data-region="topicchips"]')) {
                    var round = root.querySelector('[data-region="round"]');
                    if (round && round.dataset.view === 'topics') {
                        round.classList.remove('ffk-round--topics');
                        round.classList.add('ffk-round--cards');
                        round.dataset.view = 'cards';
                    }
                    chooseTopic(parseInt(topichash[1], 10));
                    var chips = root.querySelector('[data-region="topicchips"]');
                    setTimeout(function() {
                        chips.scrollIntoView({block: 'start'});
                    }, 300);
                }
                loadImpulse();
                return;
            }).catch(Notification.exception);
        }
    };
});
