(function (wp) {
    if (!wp || !wp.compose || !wp.hooks || !wp.element || !wp.data) {
        return;
    }

    const { createHigherOrderComponent } = wp.compose;
    const { addFilter } = wp.hooks;
    const { useEffect } = wp.element;
    const { select } = wp.data;

    // Helper function to recursively get all blocks in the editor
    const getAllBlocks = (blocks = []) => {
        let result = [];
        for (const block of blocks) {
            result.push(block);
            if (block.innerBlocks && block.innerBlocks.length > 0) {
                result = result.concat(getAllBlocks(block.innerBlocks));
            }
        }
        return result;
    };

    // Helper function to generate a random 4-character suffix
    const generateSuffix = () => Math.random().toString(36).substring(2, 6);

    const withDuplicateIdHandler = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { name, attributes, setAttributes, clientId } = props;

            useEffect(() => {
                if (name !== 'align/element' && name !== 'align/media') {
                    return;
                }

                // Retrieve all blocks currently in the editor
                const { getBlocks } = select('core/block-editor');
                const allBlocks = getAllBlocks(getBlocks());

                // Define which attributes are used as unique IDs
                const keysToCheck = name === 'align/element' ? ['elementId'] : ['videoId', 'imgId'];
                const updates = {};

                keysToCheck.forEach((key) => {
                    const currentId = attributes[key];
                    if (!currentId) {
                        return;
                    }

                    // Check if another block is already using this ID
                    let isDuplicate = allBlocks.some(
                        (block) =>
                            block.clientId !== clientId &&
                            block.attributes &&
                            block.attributes[key] === currentId
                    );

                    if (isDuplicate) {
                        let newId = currentId;
                        let maxAttempts = 10;
                        let attempts = 0;

                        // Loop to find a unique ID that is not used by any other block
                        while (isDuplicate && attempts < maxAttempts) {
                            newId = `${currentId}-${generateSuffix()}`;
                            isDuplicate = allBlocks.some(
                                (block) =>
                                    block.clientId !== clientId &&
                                    block.attributes &&
                                    block.attributes[key] === newId
                            );
                            attempts++;
                        }
                        updates[key] = newId;
                    }
                });

                // Update attributes if any duplicate IDs were resolved
                if (Object.keys(updates).length > 0) {
                    setAttributes(updates);
                }
            }, [clientId]);

            return wp.element.createElement(BlockEdit, props);
        };
    }, 'withDuplicateIdHandler');

    addFilter(
        'editor.BlockEdit',
        'align/editor-duplicate-handler',
        withDuplicateIdHandler
    );
})(window.wp);
