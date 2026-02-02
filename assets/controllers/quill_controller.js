import { Controller } from "@hotwired/stimulus"
import Quill from "quill"
import "quill/dist/quill.snow.css"

export default class extends Controller {
    static targets = ["editor", "input"]
    static values = { 
        placeholder: String,
        height: String,
        toolbar: Array
    }

    connect() {
        // Настройки по умолчанию для тулбара
        const defaultToolbar = [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'align': [] }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ]

        // Инициализация Quill
        this.quill = new Quill(this.editorTarget, {
            theme: 'snow',
            placeholder: this.placeholderValue || 'Введите текст...',
            modules: {
                toolbar: this.hasToolbarValue ? this.toolbarValue : defaultToolbar
            }
        })

        // Установка высоты редактора
        if (this.hasHeightValue) {
            this.editorTarget.querySelector('.ql-editor').style.minHeight = this.heightValue
        }

        // Загрузка существующего контента
        if (this.inputTarget.value) {
            this.quill.root.innerHTML = this.inputTarget.value
        }

        // Синхронизация с скрытым полем
        this.quill.on('text-change', () => {
            this.inputTarget.value = this.quill.root.innerHTML
        })

        // Скрытие оригинального поля
        this.inputTarget.style.display = 'none'
    }

    disconnect() {
        if (this.quill) {
            this.quill = null
        }
    }
}