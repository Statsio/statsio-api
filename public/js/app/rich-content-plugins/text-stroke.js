/**
 * Mark TipTap « contour de texte » pour le RichEditor Filament.
 * Utilise l'instance TipTap partagée (pas de bundle @tiptap/core) —
 * cf. docs Filament « Sharing the bundled TipTap/ProseMirror instance ».
 *
 * HTML émis :
 *   <span class="text-stroke" data-stroke-color="#fff"
 *         style="-webkit-text-stroke-color:#fff;-webkit-text-stroke-width:1px;paint-order:stroke fill">…</span>
 */
const { Mark } = window.FilamentRichEditor.tiptap.core

const STROKE_WIDTH = '1px'

export default Mark.create({
    name: 'textStroke',

    parseHTML() {
        return [
            {
                tag: 'span',
                getAttrs: (element) => element.classList?.contains('text-stroke'),
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        const attrs = { ...HTMLAttributes }
        const existingClass = HTMLAttributes.class
        attrs.class = ['text-stroke', existingClass].filter(Boolean).join(' ')

        const color = HTMLAttributes['data-stroke-color']
        if (typeof color === 'string' && color.length > 0) {
            const css = `-webkit-text-stroke-color: ${color}; -webkit-text-stroke-width: ${STROKE_WIDTH}; paint-order: stroke fill`
            const existingStyle =
                typeof HTMLAttributes.style === 'string' ? HTMLAttributes.style : ''
            attrs.style = existingStyle ? `${css}; ${existingStyle}` : css
        }

        return ['span', attrs, 0]
    },

    addAttributes() {
        return {
            'data-stroke-color': {
                default: null,
                parseHTML: (element) => element.getAttribute('data-stroke-color'),
                renderHTML: (attributes) => {
                    if (!attributes['data-stroke-color']) return {}
                    return { 'data-stroke-color': attributes['data-stroke-color'] }
                },
            },
        }
    },

    addCommands() {
        return {
            setTextStroke:
                ({ color }) =>
                ({ commands }) => {
                    return commands.setMark(this.name, { 'data-stroke-color': color })
                },
            unsetTextStroke:
                () =>
                ({ commands }) => {
                    return commands.unsetMark(this.name)
                },
        }
    },
})
