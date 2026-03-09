import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import axios from 'axios';
import ChatPage from '@/Pages/Chat.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({
        name: 'Head',
        setup: () => () => null,
    }),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                },
            },
        },
    }),
}));

const flushPromises = async () => {
    await Promise.resolve();
    await nextTick();
};

const createEchoMock = () => {
    const listeners = {};
    const channels = {};

    const getChannel = (name) => {
        if (channels[name]) {
            return channels[name];
        }

        channels[name] = {
            listen(event, callback) {
                listeners[`${name}:${event}`] = callback;
                return channels[name];
            },
            stopListening() {
                return channels[name];
            },
        };

        return channels[name];
    };

    window.Echo = {
        private(name) {
            return getChannel(name);
        },
    };

    return {
        emit(channel, event, payload) {
            listeners[`${channel}:${event}`]?.(payload);
        },
    };
};

const AuthenticatedLayoutStub = defineComponent({
    name: 'AuthenticatedLayout',
    setup(_, { slots }) {
        return () => h('div', {}, [slots.header?.(), slots.default?.()]);
    },
});

const UsersStub = defineComponent({
    name: 'Users',
    props: {
        users: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['select-user'],
    template: '<div />',
});

const ChatRoomsStub = defineComponent({
    name: 'ChatRooms',
    props: {
        chatRooms: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['select-chat-room'],
    template: '<div />',
});

const ConversatonStub = defineComponent({
    name: 'Conversaton',
    props: {
        hasValidSelectedChatRoom: {
            type: Boolean,
            default: false,
        },
        messageHistory: {
            type: Array,
            default: () => [],
        },
        selectedUser: {
            type: Object,
            default: null,
        },
    },
    emits: ['send-message'],
    template: '<button data-test="send-message" @click="$emit(\'send-message\', \'Hello from test\')">Send</button>',
});

const mountPage = () => {
    return mount(ChatPage, {
        global: {
            stubs: {
                AuthenticatedLayout: AuthenticatedLayoutStub,
                Users: UsersStub,
                ChatRooms: ChatRoomsStub,
                Conversaton: ConversatonStub,
                Head: true,
            },
        },
    });
};

const apiUser = { id: 2, name: 'Pat', is_online: true };
const apiRoom = {
    id: 100,
    name: 'Room Alpha',
    users: [{ id: 1, name: 'You' }, { id: 2, name: 'Pat' }],
};

describe('Chat page room messaging scaffolding', () => {
    beforeEach(() => {
        axios.get.mockImplementation((url) => {
            if (url === '/api/users') {
                return Promise.resolve({ data: { data: { users: [apiUser] } } });
            }

            if (url === '/api/chat-messages/unread-counts') {
                return Promise.resolve({ data: { data: { counts: [] } } });
            }

            if (url === '/api/chat-rooms') {
                return Promise.resolve({ data: { data: { rooms: [apiRoom] } } });
            }

            if (url === '/api/chat-rooms/invites') {
                return Promise.resolve({ data: { data: { invites: [] } } });
            }

            if (url === '/api/chat-rooms/100/messages') {
                return Promise.resolve({ data: { data: { messages: [] } } });
            }

            if (url === '/api/chat-messages/conversation/2') {
                return Promise.resolve({ data: { data: { messages: [] } } });
            }

            return Promise.resolve({ data: { data: {} } });
        });

        axios.post.mockResolvedValue({ data: { data: {} } });
    });

    it('selecting a room loads room conversation and enables room context', async () => {
        const wrapper = mountPage();
        await flushPromises();

        const chatRooms = wrapper.findComponent({ name: 'ChatRooms' });
        expect(chatRooms.exists()).toBe(true);

        chatRooms.vm.$emit('select-chat-room', apiRoom);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/chat-rooms/100/messages');
    });

    it('send-message uses room endpoint when a valid room is selected', async () => {
        axios.post.mockResolvedValueOnce({
            data: {
                data: {
                    chat_message: {
                        id: 55,
                        chat_room_id: 100,
                        from_user_id: 1,
                        from_user_name: 'You',
                        message: 'Hello from test',
                        is_mine: true,
                        created_at: '2026-03-09T00:00:00.000Z',
                    },
                },
            },
        });

        const wrapper = mountPage();
        await flushPromises();

        const chatRooms = wrapper.findComponent({ name: 'ChatRooms' });
        chatRooms.vm.$emit('select-chat-room', apiRoom);
        await flushPromises();

        wrapper.findComponent({ name: 'Conversaton' }).vm.$emit('send-message', 'Hello from test');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/chat-rooms/100/messages', {
            message: 'Hello from test',
        });
    });

    it('send-message uses direct endpoint in direct-message mode', async () => {
        window.localStorage.setItem('chat.state.1', JSON.stringify({
            requestStates: { 2: 'connected' },
            selectedUserId: null,
        }));

        const wrapper = mountPage();
        await flushPromises();

        wrapper.findComponent({ name: 'Users' }).vm.$emit('select-user', apiUser);
        await flushPromises();

        wrapper.findComponent({ name: 'Conversaton' }).vm.$emit('send-message', 'Hello from test');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/chat-message/send', {
            to_user_id: 2,
            message: 'Hello from test',
        });
    });

    it('appends incoming room realtime message once and de-duplicates by id', async () => {
        const echo = createEchoMock();
        const wrapper = mountPage();
        await flushPromises();

        wrapper.findComponent({ name: 'ChatRooms' }).vm.$emit('select-chat-room', apiRoom);
        await flushPromises();

        const channel = 'users.chat-message.1';
        const eventName = '.chat.room.message.sent';
        const payload = {
            id: 900,
            chat_room_id: 100,
            from_user_id: 2,
            from_user_name: 'Pat',
            message: 'Realtime message',
            created_at: '2026-03-09T10:00:00.000Z',
        };

        echo.emit(channel, eventName, payload);
        echo.emit(channel, eventName, payload);
        await flushPromises();

        const conversaton = wrapper.findComponent(ConversatonStub);
        expect(conversaton.props('messageHistory')).toHaveLength(1);
    });

    it.todo('selecting a user clears selected room and preserves direct message flow');
    it.todo('selectedUserMessages prefers room history when a room is selected');
    it.todo('closed/removed selected room resets selectedChatRoomId');
    it.todo('reloading room list clears selection when selected room no longer exists');
    it.todo('accepting/creating a room sets selectedChatRoomId and clears selectedUserId');
});
