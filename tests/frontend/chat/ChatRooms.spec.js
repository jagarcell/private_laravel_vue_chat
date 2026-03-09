import { mount } from '@vue/test-utils';
import ChatRooms from '@/Components/Chat/ChatRooms.vue';

const baseProps = {
    chatRooms: [
        {
            id: 10,
            name: 'Room Alpha',
            created_by_user_id: 1,
            users: [{ id: 1, name: 'You' }, { id: 2, name: 'Pat' }],
        },
    ],
    selectedChatRoomId: null,
    users: [{ id: 1, name: 'You' }, { id: 2, name: 'Pat' }],
    authenticatedUserId: 1,
    roomInvites: [],
    roomNotices: [],
};

describe('ChatRooms', () => {
    it('renders selected room style when selectedChatRoomId matches', () => {
        const wrapper = mount(ChatRooms, {
            props: {
                ...baseProps,
                selectedChatRoomId: 10,
            },
        });

        const roomItem = wrapper.find('li');

        expect(roomItem.classes()).toContain('border-blue-500');
        expect(roomItem.classes()).toContain('bg-blue-50');
    });

    it('emits select-chat-room with room payload when room is clicked', async () => {
        const wrapper = mount(ChatRooms, {
            props: baseProps,
        });

        await wrapper.find('li').trigger('click');

        const emitted = wrapper.emitted('select-chat-room');
        expect(emitted).toBeTruthy();
        expect(emitted[0][0]).toMatchObject({ id: 10, name: 'Room Alpha' });
    });
});
