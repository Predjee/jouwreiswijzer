const BLOCK_SELECTOR = 'section[role="switch"]';
const BLOCK_LIST_SELECTOR = '[class*="sortableBlockList"]';

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

const getDirectBlocks = (container) => {
    const list = container.querySelector(BLOCK_LIST_SELECTOR);

    if (!list) {
        return [];
    }

    return Array.from(list.querySelectorAll(`:scope > ${BLOCK_SELECTOR}`));
};

const expandBlock = async (block) => {
    const expandIcon = block?.querySelector('[aria-label="su-expand-vertical"]');

    if (!expandIcon) {
        return true;
    }

    expandIcon.click();
    await wait(350);

    return true;
};

export const openBlockPath = async (blockPath) => {
    const match = blockPath?.match(/^sections\[(\d+)]\.blocks\[(\d+)]$/);

    if (!match) {
        return false;
    }

    const sectionIndex = Number(match[1]);
    const blockIndex = Number(match[2]);

    const sections = getDirectBlocks(document);
    const section = sections[sectionIndex];

    if (!section) {
        return false;
    }

    await expandBlock(section);

    const blocks = getDirectBlocks(section);
    const block = blocks[blockIndex];

    if (!block) {
        return false;
    }

    await expandBlock(block);

    return true;
};

export const scrollToFeedback = async (anchorId) => {
    for (let i = 0; i < 10; i += 1) {
        const target = document.getElementById(anchorId);

        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            return true;
        }

        await wait(200);
    }

    return false;
};
