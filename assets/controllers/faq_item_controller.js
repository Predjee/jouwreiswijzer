import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['button', 'answer']

    toggle() {
        const isOpen = this.answerTarget.classList.toggle('hidden') === false

        this.element.classList.toggle('is-open', isOpen)
        this.buttonTarget.setAttribute('aria-expanded', String(isOpen))
    }
}
