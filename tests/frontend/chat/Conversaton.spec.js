import { mount } from '@vue/test-utils';
import Conversaton from '@/Components/Chat/Conversaton.vue';

const baseProps = {
    incomingRequest: null,
    selectedUser: null,
    selectedUserRequestState: 'none',
    hasValidSelectedChatRoom: false,
    messageHistory: [],
};

describe('Conversaton', () => {
    it('enables sending in room mode when hasValidSelectedChatRoom is true and message exists', async () => {
        const wrapper = mount(Conversaton, {
            props: {
                ...baseProps,
                hasValidSelectedChatRoom: true,
            },
        });

        const input = wrapper.find('input[placeholder="Type your message"]');
        await input.setValue('Hello room');

        const sendButton = wrapper.find('button[type="button"].rounded-md.bg-blue-600');
        expect(sendButton.attributes('disabled')).toBeUndefined();

        await sendButton.trigger('click');
        expect(wrapper.emitted('send-message')).toEqual([['Hello room']]);
    });

    it('keeps send disabled when message input is empty', async () => {
        const wrapper = mount(Conversaton, {
            props: {
                ...baseProps,
                hasValidSelectedChatRoom: true,
            },
        });

        const sendButton = wrapper.find('button[type="button"].rounded-md.bg-blue-600');
        expect(sendButton.attributes('disabled')).toBeDefined();
        expect(wrapper.emitted('send-message')).toBeFalsy();
    });
});
