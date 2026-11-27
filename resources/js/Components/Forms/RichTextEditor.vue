<script setup lang="ts">
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Rich text (Tiptap 3). The toolbar offers only what the server keeps (App\Support\RichText whitelist):
 * paragraphs, headings h2–h4, bold/italic/underline, lists, quote, links. "HTML" switches to the source for
 * imported content. CSP: Tiptap's injected <style> is off (styles live in admin.css → .rte).
 */
const props = withDefaults(
    defineProps<{ modelValue: string | null | undefined; id: string; placeholder?: string; invalid?: boolean; describedby?: string; minHeight?: 'sm' | 'md' | 'lg' }>(),
    { placeholder: '', invalid: false, describedby: undefined, minHeight: 'md' },
);
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const { t } = useTrans();

const source = ref(false);
const linkOpen = ref(false);
const linkUrl = ref('');
const linkInput = ref<HTMLInputElement | null>(null);

const editor = useEditor({
    content: props.modelValue ?? '',
    injectCSS: false,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            code: false,
            codeBlock: false,
            strike: false,
            horizontalRule: false,
            link: { openOnClick: false, autolink: true, protocols: ['mailto', 'tel'], HTMLAttributes: { rel: 'noopener nofollow', target: null } },
        }),
        Placeholder.configure({ placeholder: () => props.placeholder }),
    ],
    editorProps: {
        attributes: {
            id: props.id,
            // No dir="auto": an empty editor would resolve to LTR. The direction is inherited from the language tab
            // (<LocaleTabs> panel: rtl for ar/he, ltr for en) or the page.
            role: 'textbox',
            'aria-multiline': 'true',
            ...(props.describedby ? { 'aria-describedby': props.describedby } : {}),
            class: 'rte-content',
        },
    },
    onUpdate: ({ editor: e }) => emit('update:modelValue', e.isEmpty ? '' : e.getHTML()),
});

// External changes (form reset, locale copy) → editor, without echoing our own updates.
watch(
    () => props.modelValue,
    (value) => {
        const e = editor.value;
        if (e && !source.value && (value ?? '') !== (e.isEmpty ? '' : e.getHTML())) e.commands.setContent(value ?? '', { emitUpdate: false });
    },
);
watch(
    () => props.invalid,
    (bad) => editor.value?.view.dom.setAttribute('aria-invalid', bad ? 'true' : 'false'),
    { immediate: true },
);
onBeforeUnmount(() => editor.value?.destroy());

type Tool = { key: string; icon?: string; text?: string; active: () => boolean; run: () => void };
const tools = computed<Tool[][]>(() => {
    const e = editor.value;
    if (!e) return [];
    const chain = () => e.chain().focus();
    return [
        [2, 3].map((level) => ({
            key: `h${level}`,
            text: `H${level}`,
            active: () => e.isActive('heading', { level }),
            run: () => chain().toggleHeading({ level: level as 2 | 3 }).run(),
        })),
        [
            { key: 'bold', icon: 'text-bold', active: () => e.isActive('bold'), run: () => chain().toggleBold().run() },
            { key: 'italic', icon: 'text-italic', active: () => e.isActive('italic'), run: () => chain().toggleItalic().run() },
            { key: 'underline', icon: 'text-underline', active: () => e.isActive('underline'), run: () => chain().toggleUnderline().run() },
        ],
        [
            { key: 'bullet_list', icon: 'text-circle', active: () => e.isActive('bulletList'), run: () => chain().toggleBulletList().run() },
            { key: 'ordered_list', icon: 'text-number', active: () => e.isActive('orderedList'), run: () => chain().toggleOrderedList().run() },
            { key: 'quote', icon: 'message-text', active: () => e.isActive('blockquote'), run: () => chain().toggleBlockquote().run() },
            { key: 'link', icon: 'fasten', active: () => e.isActive('link'), run: openLink },
        ],
        [
            { key: 'clear', icon: 'eraser', active: () => false, run: () => chain().unsetAllMarks().clearNodes().run() },
            { key: 'undo', icon: 'arrow-circle-left', active: () => false, run: () => chain().undo().run() },
            { key: 'redo', icon: 'arrow-circle-right', active: () => false, run: () => chain().redo().run() },
        ],
    ];
});

async function openLink(): Promise<void> {
    linkUrl.value = (editor.value?.getAttributes('link').href as string | undefined) ?? 'https://';
    linkOpen.value = true;
    await nextTick();
    linkInput.value?.select();
}
function applyLink(): void {
    const url = linkUrl.value.trim();
    const chain = editor.value?.chain().focus().extendMarkRange('link');
    if (!chain) return;
    (url && url !== 'https://' ? chain.setLink({ href: url }) : chain.unsetLink()).run();
    linkOpen.value = false;
}
function toggleSource(): void {
    if (source.value) editor.value?.commands.setContent(props.modelValue ?? '', { emitUpdate: false });
    source.value = !source.value;
}
</script>

<template>
    <div :class="['rte', `rte-${minHeight}`, { 'rte-invalid': invalid }]">
        <div class="rte-toolbar" role="toolbar" :aria-label="t('forms.editor_toolbar')" :aria-controls="id">
            <template v-for="(group, g) in tools" :key="g">
                <span v-if="g > 0" class="rte-sep" aria-hidden="true" />
                <button
                    v-for="tool in group"
                    :key="tool.key"
                    type="button"
                    class="rte-btn"
                    :class="{ 'is-active': tool.active() }"
                    :aria-pressed="tool.active()"
                    :disabled="source"
                    :title="t(`forms.editor.${tool.key}`)"
                    :aria-label="t(`forms.editor.${tool.key}`)"
                    @click="tool.run"
                >
                    <KtIcon v-if="tool.icon" :name="tool.icon" :class="{ 'rtl:-scale-x-100': tool.key === 'undo' || tool.key === 'redo' }" />
                    <span v-else class="text-xs font-bold">{{ tool.text }}</span>
                </button>
            </template>
            <button type="button" class="rte-btn ms-auto px-2 font-mono text-[11px]" :class="{ 'is-active': source }" :aria-pressed="source" @click="toggleSource">
                HTML
            </button>
        </div>

        <!-- Not a <form>: this editor lives inside the page form. -->
        <div v-if="linkOpen" class="rte-link" @keydown.enter.prevent="applyLink" @keydown.esc.stop="linkOpen = false">
            <input ref="linkInput" v-model="linkUrl" type="url" dir="ltr" class="kt-input kt-input-sm flex-1" :aria-label="t('forms.editor.link')" />
            <button type="button" class="kt-btn kt-btn-sm" @click="applyLink">{{ t('forms.apply') }}</button>
            <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" @click="linkOpen = false">{{ t('common.cancel') }}</button>
        </div>

        <textarea
            v-if="source"
            :id="`${id}-source`"
            class="rte-source"
            dir="ltr"
            spellcheck="false"
            :value="modelValue ?? ''"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <EditorContent v-else :editor="editor" />
    </div>
</template>
