document.addEventListener('DOMContentLoaded', function() {
    // Improved tab functionality - similar to documents.php style
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons and panes
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab') + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // AI and HTML editor/preview functionality
    const aiTemplateContent = document.getElementById('ai_template_content');
    const aiCodePreview = document.getElementById('aiCodePreview');
    const aiRefreshPreviewBtn = document.getElementById('aiRefreshPreviewBtn');
    const aiFormatCodeBtn = document.getElementById('aiFormatCodeBtn');
    
    if (aiRefreshPreviewBtn) {
        aiRefreshPreviewBtn.addEventListener('click', function() {
            updateAICodePreview();
        });
    }
    
    if (aiFormatCodeBtn) {
        aiFormatCodeBtn.addEventListener('click', function() {
            // Simple HTML formatting
            const code = aiTemplateContent.value;
            aiTemplateContent.value = formatHTML(code);
            updateAICodePreview();
        });
    }
    
    // Auto-update preview when typing with debounce
    if (aiTemplateContent) {
        let typingTimer;
        aiTemplateContent.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(updateAICodePreview, 1000);
        });
    }
    
    function updateAICodePreview() {
        if (aiCodePreview && aiTemplateContent) {
            aiCodePreview.innerHTML = aiTemplateContent.value || '<p class="preview-placeholder">HTML preview will appear here</p>';
        }
    }
    
    // Initialize AI preview on page load
    if (aiTemplateContent && aiCodePreview) {
        updateAICodePreview();
    }
    
    // AI template generation
    const aiPromptInput = document.getElementById('ai-prompt');
    const generateTemplateBtn = document.getElementById('generateTemplateBtn');
    const aiGenerating = document.querySelector('.ai-generating');
    
    // AI suggestion tags
    const aiSuggestionTags = document.querySelectorAll('.ai-suggestion-tag');
    
    if (generateTemplateBtn) {
        generateTemplateBtn.addEventListener('click', function() {
            generateAITemplate();
        });
    }
    
    if (aiSuggestionTags) {
        aiSuggestionTags.forEach(tag => {
            tag.addEventListener('click', function() {
                aiPromptInput.value = this.textContent;
            });
        });
    }
    
    function generateAITemplate() {
        const prompt = aiPromptInput.value.trim();
        if (!prompt) {
            alert('Please enter a description for your template');
            return;
        }
        
        // Show generating indicator
        if (aiGenerating) {
            aiGenerating.classList.remove('d-none');
        }
        
        // Simulate AI generation with templates
        setTimeout(() => {
            // Hide generating indicator
            if (aiGenerating) {
                aiGenerating.classList.add('d-none');
            }
            
            const template = generateSampleTemplate(prompt);
            
            // Update the AI tab's code editor and preview
            if (aiTemplateContent) {
                aiTemplateContent.value = template;
                updateAICodePreview();
            }
        }, 1500);
    }
    
    // Component builder functionality
    const templateCanvas = document.getElementById('templateCanvas');
    const componentItems = document.querySelectorAll('.component-item');
    const propertyPanel = document.getElementById('componentProperties');
    const previewBtn = document.getElementById('previewBtn');
    
    // Preview button functionality
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            updateComponentPreview();
        });
    }
    
    // Make components draggable
    if (componentItems) {
        componentItems.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
        });
    }
    
    // Set up canvas drop area
    if (templateCanvas) {
        templateCanvas.addEventListener('dragover', handleDragOver);
        templateCanvas.addEventListener('drop', handleDrop);
        
        // Initialize canvas with a container if it's empty
        if (templateCanvas.children.length === 0) {
            addContainerToCanvas();
        }
    }
    
    function handleDragStart(e) {
        e.dataTransfer.setData('component', this.getAttribute('data-component'));
    }
    
    function handleDragOver(e) {
        e.preventDefault();
    }
    
    function handleDrop(e) {
        e.preventDefault();
        const componentType = e.dataTransfer.getData('component');
        
        if (componentType) {
            addComponentToCanvas(componentType, e.clientX, e.clientY);
            // Update the preview
            updateComponentPreview();
        }
    }
    
    function addComponentToCanvas(componentType, x, y) {
        let component;
        
        switch(componentType) {
            case 'container':
                component = createContainerComponent();
                break;
            case 'row':
                component = createRowComponent();
                break;
            case 'grid':
                component = createGridComponent();
                break;
            case 'text':
                component = createTextComponent();
                break;
            case 'image':
                component = createImageComponent();
                break;
            case 'imagePlaceholder':
                component = createImagePlaceholderComponent();
                break;
            case 'button':
                component = createButtonComponent();
                break;
            case 'divider':
                component = createDividerComponent();
                break;
            case 'spacer':
                component = createSpacerComponent();
                break;
            default:
                return;
        }
        
        if (component) {
            // Find closest drop target
            let dropTarget = templateCanvas;
            
            // If there are containers, find the closest one
            if (templateCanvas.querySelector('.component-container')) {
                const containers = templateCanvas.querySelectorAll('.component-container');
                let el = document.elementFromPoint(x, y);
                
                // Look up the DOM to find a container
                while (el && el !== templateCanvas) {
                    if (el.classList.contains('component-container')) {
                        dropTarget = el;
                        break;
                    }
                    el = el.parentElement;
                }
            }
            
            // Add component to drop target
            dropTarget.appendChild(component);
            
            // Select the newly added component
            selectComponent(component);
        }
    }
    
    function addContainerToCanvas() {
        const container = createContainerComponent();
        templateCanvas.appendChild(container);
    }
    
    function createContainerComponent() {
        const container = document.createElement('div');
        container.className = 'canvas-component component-container';
        container.setAttribute('data-component-type', 'container');
        
        // Add container controls
        addComponentControls(container);
        
        return container;
    }
    
    function createRowComponent() {
        const row = document.createElement('div');
        row.className = 'canvas-component component-row';
        row.setAttribute('data-component-type', 'row');
        
        // Create two columns by default
        const col1 = document.createElement('div');
        col1.className = 'component-col';
        
        const col2 = document.createElement('div');
        col2.className = 'component-col';
        
        row.appendChild(col1);
        row.appendChild(col2);
        
        // Make columns droppable
        makeDroppable(col1);
        makeDroppable(col2);
        
        // Add row controls
        addComponentControls(row);
        
        return row;
    }
    
    function makeDroppable(element) {
        element.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        element.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        element.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const componentType = e.dataTransfer.getData('component');
            if (componentType) {
                let component;
                
                switch(componentType) {
                    case 'text':
                        component = createTextComponent();
                        break;
                    case 'image':
                        component = createImageComponent();
                        break;
                    case 'button':
                        component = createButtonComponent();
                        break;
                    case 'divider':
                        component = createDividerComponent();
                        break;
                    case 'spacer':
                        component = createSpacerComponent();
                        break;
                    default:
                        return;
                }
                
                if (component) {
                    this.appendChild(component);
                    selectComponent(component);
                    updateComponentPreview();
                }
            }
        });
    }
    
    function createTextComponent() {
        const text = document.createElement('div');
        text.className = 'canvas-component component-text';
        text.setAttribute('data-component-type', 'text');
        text.innerHTML = '<p>Double-click to edit text</p>';
        
        // Make text editable on double click
        text.addEventListener('dblclick', function() {
            if (!this.classList.contains('editing')) {
                this.setAttribute('contenteditable', 'true');
                this.classList.add('editing');
                this.focus();
            }
        });
        
        // Stop editing on blur
        text.addEventListener('blur', function() {
            this.removeAttribute('contenteditable');
            this.classList.remove('editing');
            // Update preview after editing
            updateComponentPreview();
        });
        
        // Add text controls
        addComponentControls(text);
        
        return text;
    }
    
    function createImageComponent() {
        const image = document.createElement('div');
        image.className = 'canvas-component component-image';
        image.setAttribute('data-component-type', 'image');
        
        // Default placeholder image
        image.innerHTML = '<img src="https://via.placeholder.com/300x150" alt="Image">';
        
        // Add image controls
        addComponentControls(image);
        
        return image;
    }
    
    function createButtonComponent() {
        const button = document.createElement('div');
        button.className = 'canvas-component component-button';
        button.setAttribute('data-component-type', 'button');
        
        button.innerHTML = '<a href="#" class="btn">Click Here</a>';
        
        // Add button controls
        addComponentControls(button);
        
        return button;
    }
    
    function createDividerComponent() {
        const divider = document.createElement('div');
        divider.className = 'canvas-component component-divider';
        divider.setAttribute('data-component-type', 'divider');
        divider.style.borderTop = '1px solid #dddddd';
        divider.style.margin = '10px 0';
        
        // Add divider controls
        addComponentControls(divider);
        
        return divider;
    }
    
    function createSpacerComponent() {
        const spacer = document.createElement('div');
        spacer.className = 'canvas-component component-spacer';
        spacer.setAttribute('data-component-type', 'spacer');
        spacer.style.height = '20px';
        
        // Add spacer controls
        addComponentControls(spacer);
        
        return spacer;
    }
    
    function createGridComponent() {
        const grid = document.createElement('div');
        grid.className = 'canvas-component component-grid';
        grid.setAttribute('data-component-type', 'grid');
        
        // Create a default 2x2 grid
        for (let i = 0; i < 2; i++) {
            const row = document.createElement('div');
            row.className = 'grid-row';
            
            for (let j = 0; j < 2; j++) {
                const cell = document.createElement('div');
                cell.className = 'grid-cell';
                cell.setAttribute('data-row', i);
                cell.setAttribute('data-col', j);
                
                // Make cells droppable
                makeDroppable(cell);
                
                row.appendChild(cell);
            }
            
            grid.appendChild(row);
        }
        
        // Add grid controls
        addComponentControls(grid);
        
        return grid;
    }
    
    function createImagePlaceholderComponent() {
        const placeholder = document.createElement('div');
        placeholder.className = 'canvas-component component-image-placeholder';
        placeholder.setAttribute('data-component-type', 'imagePlaceholder');
        
        // Create placeholder content
        placeholder.innerHTML = `
            <div class="image-upload-area">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Drag & drop an image here or click to upload</p>
                <input type="file" class="image-file-input" accept="image/*" style="display: none;">
            </div>
            <img src="" alt="Uploaded Image" class="uploaded-image" style="display: none; max-width: 100%;">
        `;
        
        // Add upload functionality
        const uploadArea = placeholder.querySelector('.image-upload-area');
        const fileInput = placeholder.querySelector('.image-file-input');
        const uploadedImage = placeholder.querySelector('.uploaded-image');
        
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    uploadedImage.src = e.target.result;
                    uploadedImage.style.display = 'block';
                    uploadArea.style.display = 'none';
                };
                
                reader.readAsDataURL(this.files[0]);
                updateComponentPreview();
            }
        });
        
        // Add drag and drop for images
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    uploadedImage.src = e.target.result;
                    uploadedImage.style.display = 'block';
                    uploadArea.style.display = 'none';
                };
                
                reader.readAsDataURL(e.dataTransfer.files[0]);
                updateComponentPreview();
            }
        });
        
        // Add component controls
        addComponentControls(placeholder);
        
        return placeholder;
    }
    
    function addComponentControls(component) {
        const controls = document.createElement('div');
        controls.className = 'component-controls';
        
        // Move up button
        const moveUpBtn = document.createElement('button');
        moveUpBtn.className = 'component-control-btn';
        moveUpBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        moveUpBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            moveComponent(component, 'up');
            updateComponentPreview();
        });
        
        // Move down button
        const moveDownBtn = document.createElement('button');
        moveDownBtn.className = 'component-control-btn';
        moveDownBtn.innerHTML = '<i class="fas fa-arrow-down"></i>';
        moveDownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            moveComponent(component, 'down');
            updateComponentPreview();
        });
        
        // Delete button
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'component-control-btn';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Are you sure you want to delete this component?')) {
                component.remove();
                
                // Clear property panel
                if (propertyPanel) {
                    propertyPanel.innerHTML = '<p class="property-placeholder">Select a component to edit its properties</p>';
                }
                
                // Update preview
                updateComponentPreview();
            }
        });
        
        controls.appendChild(moveUpBtn);
        controls.appendChild(moveDownBtn);
        controls.appendChild(deleteBtn);
        
        component.appendChild(controls);
        
        // Make component selectable
        component.addEventListener('click', function() {
            selectComponent(this);
        });
    }
    
    function moveComponent(component, direction) {
        if (direction === 'up') {
            const prev = component.previousElementSibling;
            if (prev) {
                component.parentNode.insertBefore(component, prev);
            }
        } else if (direction === 'down') {
            const next = component.nextElementSibling;
            if (next) {
                component.parentNode.insertBefore(next, component);
            }
        }
    }
    
    function selectComponent(component) {
        // Deselect all components
        const selectedComponents = document.querySelectorAll('.canvas-component.selected');
        selectedComponents.forEach(c => c.classList.remove('selected'));
        
        // Select this component
        component.classList.add('selected');
        
        // Show properties for this component
        showComponentProperties(component);
    }
    
    function showComponentProperties(component) {
        if (!propertyPanel) return;
        
        const componentType = component.getAttribute('data-component-type');
        let propertiesHTML = '';
        
        switch (componentType) {
            case 'container':
                propertiesHTML = getContainerProperties(component);
                break;
            case 'row':
                propertiesHTML = getRowProperties(component);
                break;
            case 'grid':
                propertiesHTML = getGridProperties(component);
                break;
            case 'text':
                propertiesHTML = getTextProperties(component);
                break;
            case 'image':
                propertiesHTML = getImageProperties(component);
                break;
            case 'imagePlaceholder':
                propertiesHTML = getImagePlaceholderProperties(component);
                break;
            case 'button':
                propertiesHTML = getButtonProperties(component);
                break;
            case 'divider':
                propertiesHTML = getDividerProperties(component);
                break;
            case 'spacer':
                propertiesHTML = getSpacerProperties(component);
                break;
            default:
                propertiesHTML = '<p class="property-placeholder">No properties available for this component</p>';
        }
        
        propertyPanel.innerHTML = propertiesHTML;
        
        // Add event listeners to the newly created property inputs
        addPropertyEventListeners(component);
    }
    
    function getContainerProperties(component) {
        const backgroundColor = component.style.backgroundColor || '';
        const padding = component.style.padding || '10px';
        const borderRadius = component.style.borderRadius || '0px';
        const borderColor = component.style.borderColor || '';
        const borderWidth = component.style.borderWidth || '0px';
        const borderStyle = component.style.borderStyle || 'none';
        const width = component.style.width || '100%';
        const maxWidth = component.style.maxWidth || '';
        const boxShadow = component.style.boxShadow || '';
        
        return `
            <div class="property-group">
                <h5>Container Properties</h5>
                
                <div class="property-row">
                    <label>Width:</label>
                    <input type="text" class="property-input" data-property="width" value="${width}">
            </div>
                
                <div class="property-row">
                    <label>Max Width:</label>
                    <input type="text" class="property-input" data-property="maxWidth" value="${maxWidth}">
            </div>
                
                <div class="property-row">
                    <label>Background Color:</label>
                    <input type="color" class="property-input" data-property="backgroundColor" value="${backgroundColor || '#ffffff'}">
            </div>
                
                <div class="property-row">
                    <label>Padding:</label>
                    <div class="input-with-units">
                        <input type="text" class="property-input" data-property="padding" value="${padding}">
            </div>
                </div>
                
                <div class="property-row">
                    <label>Border Radius:</label>
                    <div class="input-with-units">
                        <input type="text" class="property-input" data-property="borderRadius" value="${borderRadius}">
                    </div>
                </div>
                
                <div class="property-row">
                    <label>Border Width:</label>
                    <div class="input-with-units">
                        <input type="text" class="property-input" data-property="borderWidth" value="${borderWidth}">
                    </div>
                </div>
                
                <div class="property-row">
                    <label>Border Color:</label>
                    <input type="color" class="property-input" data-property="borderColor" value="${borderColor || '#000000'}">
                </div>
                
                <div class="property-row">
                    <label>Border Style:</label>
                    <select class="property-input" data-property="borderStyle">
                        <option value="none" ${borderStyle === 'none' ? 'selected' : ''}>None</option>
                        <option value="solid" ${borderStyle === 'solid' ? 'selected' : ''}>Solid</option>
                        <option value="dashed" ${borderStyle === 'dashed' ? 'selected' : ''}>Dashed</option>
                        <option value="dotted" ${borderStyle === 'dotted' ? 'selected' : ''}>Dotted</option>
                    </select>
                </div>
                
                <div class="property-row">
                    <label>Box Shadow:</label>
                    <select class="property-input" data-property="boxShadow">
                        <option value="none" ${!boxShadow ? 'selected' : ''}>None</option>
                        <option value="0 2px 5px rgba(0,0,0,0.1)" ${boxShadow === '0 2px 5px rgba(0,0,0,0.1)' ? 'selected' : ''}>Light</option>
                        <option value="0 4px 8px rgba(0,0,0,0.1)" ${boxShadow === '0 4px 8px rgba(0,0,0,0.1)' ? 'selected' : ''}>Medium</option>
                        <option value="0 6px 12px rgba(0,0,0,0.15)" ${boxShadow === '0 6px 12px rgba(0,0,0,0.15)' ? 'selected' : ''}>Strong</option>
                    </select>
                </div>
                
                <button type="button" class="btn property-btn add-row-btn">Add Row</button>
                <button type="button" class="btn property-btn add-grid-btn">Add Grid Layout</button>
            </div>
        `;
    }
    
    function getRowProperties(component) {
        const columnGap = component.style.columnGap || '10px';
        const marginTop = component.style.marginTop || '0px';
        const marginBottom = component.style.marginBottom || '0px';
        
        return `
            <div class="property-group">
                <h5>Row Properties</h5>
                
                <div class="property-row">
                    <label>Column Gap:</label>
                    <input type="text" class="property-input" data-property="columnGap" value="${columnGap}">
                </div>
                
                <div class="property-row">
                    <label>Margin Top:</label>
                    <input type="text" class="property-input" data-property="marginTop" value="${marginTop}">
                </div>
                
                <div class="property-row">
                    <label>Margin Bottom:</label>
                    <input type="text" class="property-input" data-property="marginBottom" value="${marginBottom}">
                </div>
                
                <div class="property-row">
                    <label>Columns:</label>
                    <select class="property-input" data-property="columns">
                        <option value="2" selected>2 Columns</option>
                        <option value="3">3 Columns</option>
                        <option value="4">4 Columns</option>
                    </select>
                </div>
                
                <button type="button" class="btn property-btn update-columns-btn">Update Columns</button>
            </div>
        `;
    }
    
    function getTextProperties(component) {
        const color = component.style.color || '';
        const fontSize = component.style.fontSize || '';
        const fontWeight = component.style.fontWeight || '';
        const textAlign = component.style.textAlign || '';
        const lineHeight = component.style.lineHeight || '';
        const paddingTop = component.style.paddingTop || '';
        const paddingBottom = component.style.paddingBottom || '';
        
        return `
            <div class="property-group">
                <h5>Text Properties</h5>
                
                <div class="property-row">
                    <label>Text Color:</label>
                    <input type="color" class="property-input" data-property="color" value="${color || '#000000'}">
            </div>
                
                <div class="property-row">
                    <label>Font Size:</label>
                    <input type="text" class="property-input" data-property="fontSize" value="${fontSize || '16px'}">
            </div>
                
                <div class="property-row">
                    <label>Font Weight:</label>
                    <select class="property-input" data-property="fontWeight">
                        <option value="normal" ${fontWeight === 'normal' ? 'selected' : ''}>Normal</option>
                        <option value="bold" ${fontWeight === 'bold' ? 'selected' : ''}>Bold</option>
                        <option value="300" ${fontWeight === '300' ? 'selected' : ''}>Light</option>
                        <option value="500" ${fontWeight === '500' ? 'selected' : ''}>Medium</option>
                        <option value="600" ${fontWeight === '600' ? 'selected' : ''}>Semibold</option>
                </select>
            </div>
                
                <div class="property-row">
                    <label>Text Align:</label>
                    <select class="property-input" data-property="textAlign">
                        <option value="left" ${textAlign === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${textAlign === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${textAlign === 'right' ? 'selected' : ''}>Right</option>
                        <option value="justify" ${textAlign === 'justify' ? 'selected' : ''}>Justify</option>
                    </select>
                </div>
                
                <div class="property-row">
                    <label>Line Height:</label>
                    <input type="text" class="property-input" data-property="lineHeight" value="${lineHeight || '1.5'}">
                </div>
                
                <div class="property-row">
                    <label>Padding Top:</label>
                    <input type="text" class="property-input" data-property="paddingTop" value="${paddingTop || '0px'}">
                </div>
                
                <div class="property-row">
                    <label>Padding Bottom:</label>
                    <input type="text" class="property-input" data-property="paddingBottom" value="${paddingBottom || '0px'}">
                </div>
            </div>
        `;
    }
    
    function getImageProperties(component) {
        // Find the img element
        const img = component.querySelector('img');
        const imgSrc = img ? img.getAttribute('src') : '';
        const imgAlt = img ? img.getAttribute('alt') : '';
        const imgWidth = img ? img.style.width : '';
        const imgHeight = img ? img.style.height : '';
        const alignSelf = component.style.alignSelf || '';
        
        return `
            <div class="property-group">
                <h5>Image Properties</h5>
                
                <div class="property-row">
                    <label>Image URL:</label>
                    <input type="text" class="property-input" data-target="img" data-property="src" value="${imgSrc}">
            </div>
                
                <div class="property-row">
                    <label>Alt Text:</label>
                    <input type="text" class="property-input" data-target="img" data-property="alt" value="${imgAlt}">
            </div>
                
                <div class="property-row">
                    <label>Width:</label>
                    <input type="text" class="property-input" data-target="img" data-property="width" value="${imgWidth || 'auto'}">
            </div>
                
                <div class="property-row">
                    <label>Height:</label>
                    <input type="text" class="property-input" data-target="img" data-property="height" value="${imgHeight || 'auto'}">
                </div>
                
                <div class="property-row">
                    <label>Alignment:</label>
                    <select class="property-input" data-property="alignSelf">
                        <option value="flex-start" ${alignSelf === 'flex-start' ? 'selected' : ''}>Left</option>
                        <option value="center" ${alignSelf === 'center' ? 'selected' : ''}>Center</option>
                        <option value="flex-end" ${alignSelf === 'flex-end' ? 'selected' : ''}>Right</option>
                    </select>
                </div>
                
                <div class="property-row">
                    <label>Border Radius:</label>
                    <input type="text" class="property-input" data-target="img" data-property="borderRadius" value="${img && img.style.borderRadius || '0px'}">
                </div>
            </div>
        `;
    }
    
    function getButtonProperties(component) {
        // Find the button element
        const btn = component.querySelector('.btn');
        const btnText = btn ? btn.textContent : '';
        const btnHref = btn ? btn.getAttribute('href') : '#';
        const btnColor = btn ? btn.style.color : '';
        const btnBgColor = btn ? btn.style.backgroundColor : '';
        const btnPadding = btn ? btn.style.padding : '';
        const btnBorderRadius = btn ? btn.style.borderRadius : '';
        const btnFontSize = btn ? btn.style.fontSize : '';
        const btnTextAlign = component.style.textAlign || '';
        
        return `
            <div class="property-group">
                <h5>Button Properties</h5>
                
                <div class="property-row">
                    <label>Button Text:</label>
                    <input type="text" class="property-input" data-target="btn" data-property="text" value="${btnText}">
            </div>
                
                <div class="property-row">
                    <label>Link URL:</label>
                    <input type="text" class="property-input" data-target="btn" data-property="href" value="${btnHref}">
            </div>
                
                <div class="property-row">
                    <label>Text Color:</label>
                    <input type="color" class="property-input" data-target="btn" data-property="color" value="${btnColor || '#ffffff'}">
            </div>
                
                <div class="property-row">
                    <label>Background Color:</label>
                    <input type="color" class="property-input" data-target="btn" data-property="backgroundColor" value="${btnBgColor || '#007bff'}">
            </div>
                
                <div class="property-row">
                    <label>Padding:</label>
                    <input type="text" class="property-input" data-target="btn" data-property="padding" value="${btnPadding || '8px 16px'}">
            </div>
                
                <div class="property-row">
                    <label>Border Radius:</label>
                    <input type="text" class="property-input" data-target="btn" data-property="borderRadius" value="${btnBorderRadius || '4px'}">
                </div>
                
                <div class="property-row">
                    <label>Font Size:</label>
                    <input type="text" class="property-input" data-target="btn" data-property="fontSize" value="${btnFontSize || '16px'}">
                </div>
                
                <div class="property-row">
                    <label>Alignment:</label>
                    <select class="property-input" data-property="textAlign">
                        <option value="left" ${btnTextAlign === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${btnTextAlign === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${btnTextAlign === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </div>
            </div>
        `;
    }
    
    function getDividerProperties(component) {
        const borderStyle = component.style.borderTopStyle || 'solid';
        const borderColor = component.style.borderTopColor || '';
        const borderWidth = component.style.borderTopWidth || '1px';
        const marginTop = component.style.marginTop || '10px';
        const marginBottom = component.style.marginBottom || '10px';
        
        return `
            <div class="property-group">
                <h5>Divider Properties</h5>
                
                <div class="property-row">
                    <label>Style:</label>
                    <select class="property-input" data-property="borderTopStyle">
                        <option value="solid" ${borderStyle === 'solid' ? 'selected' : ''}>Solid</option>
                        <option value="dashed" ${borderStyle === 'dashed' ? 'selected' : ''}>Dashed</option>
                        <option value="dotted" ${borderStyle === 'dotted' ? 'selected' : ''}>Dotted</option>
                    </select>
            </div>
                
                <div class="property-row">
                    <label>Color:</label>
                    <input type="color" class="property-input" data-property="borderTopColor" value="${borderColor || '#dddddd'}">
            </div>
                
                <div class="property-row">
                    <label>Width:</label>
                    <input type="text" class="property-input" data-property="borderTopWidth" value="${borderWidth}">
                </div>
                
                <div class="property-row">
                    <label>Margin Top:</label>
                    <input type="text" class="property-input" data-property="marginTop" value="${marginTop}">
                </div>
                
                <div class="property-row">
                    <label>Margin Bottom:</label>
                    <input type="text" class="property-input" data-property="marginBottom" value="${marginBottom}">
                </div>
            </div>
        `;
    }
    
    function getSpacerProperties(component) {
        const height = component.style.height || '20px';
        
        return `
            <div class="property-group">
                <h5>Spacer Properties</h5>
                
                <div class="property-row">
                    <label>Height:</label>
                    <input type="text" class="property-input" data-property="height" value="${height}">
                </div>
            </div>
        `;
    }
    
    function getGridProperties(component) {
        const rows = component.querySelectorAll('.grid-row').length;
        const columns = component.querySelector('.grid-row') ? component.querySelector('.grid-row').children.length : 0;
        const gridGap = component.style.gap || '10px';
        const backgroundColor = component.style.backgroundColor || '';
        const padding = component.style.padding || '10px';
        const borderRadius = component.style.borderRadius || '0px';
        
        return `
            <div class="property-group">
                <h5>Grid Properties</h5>
                
                <div class="property-row">
                    <label>Rows:</label>
                    <input type="number" min="1" max="6" class="property-input" data-property="rows" value="${rows}">
                </div>
                
                <div class="property-row">
                    <label>Columns:</label>
                    <input type="number" min="1" max="6" class="property-input" data-property="columns" value="${columns}">
                </div>
                
                <div class="property-row">
                    <label>Grid Gap:</label>
                    <input type="text" class="property-input" data-property="gap" value="${gridGap}">
                </div>
                
                <div class="property-row">
                    <label>Background Color:</label>
                    <input type="color" class="property-input" data-property="backgroundColor" value="${backgroundColor || '#ffffff'}">
                </div>
                
                <div class="property-row">
                    <label>Padding:</label>
                    <input type="text" class="property-input" data-property="padding" value="${padding}">
                </div>
                
                <div class="property-row">
                    <label>Border Radius:</label>
                    <input type="text" class="property-input" data-property="borderRadius" value="${borderRadius}">
                </div>
                
                <button type="button" class="btn property-btn update-grid-btn">Update Grid</button>
            </div>
        `;
    }
    
    function getImagePlaceholderProperties(component) {
        const width = component.style.width || '';
        const height = component.style.height || '';
        const borderRadius = component.style.borderRadius || '0px';
        const textAlign = component.style.textAlign || 'center';
        
        return `
            <div class="property-group">
                <h5>Image Placeholder Properties</h5>
                
                <div class="property-row">
                    <label>Width:</label>
                    <input type="text" class="property-input" data-property="width" value="${width}">
                </div>
                
                <div class="property-row">
                    <label>Height:</label>
                    <input type="text" class="property-input" data-property="height" value="${height}">
                </div>
                
                <div class="property-row">
                    <label>Border Radius:</label>
                    <input type="text" class="property-input" data-property="borderRadius" value="${borderRadius}">
                </div>
                
                <div class="property-row">
                    <label>Alignment:</label>
                    <select class="property-input" data-property="textAlign">
                        <option value="left" ${textAlign === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${textAlign === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${textAlign === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </div>
                
                <button type="button" class="btn property-btn reset-placeholder-btn">Reset Placeholder</button>
            </div>
        `;
    }
    
    function addPropertyEventListeners(component) {
        const propertyInputs = document.querySelectorAll('.property-input');
        
        propertyInputs.forEach(input => {
            input.addEventListener('change', function() {
                const property = this.getAttribute('data-property');
                const targetSelector = this.getAttribute('data-target');
                let value = this.value;
                
                // Apply the property change to the component or its child element
                if (targetSelector) {
                    const targetElement = component.querySelector(targetSelector) || 
                                          component.querySelector('.' + targetSelector);
                    
                    if (targetElement) {
                        if (property === 'text') {
                            // Special case for text content
                            targetElement.textContent = value;
                        } else if (property === 'src' || property === 'href' || property === 'alt') {
                            // Attributes
                            targetElement.setAttribute(property, value);
            } else {
                            // CSS properties
                            targetElement.style[property] = value;
            }
        }
        } else {
                    // Apply directly to the component
            component.style[property] = value;
        }
                
                // Update the preview
                updateComponentPreview();
            });
        });
        
        // Add event listener for container buttons
        if (component.getAttribute('data-component-type') === 'container') {
            const addRowBtn = document.querySelector('.add-row-btn');
            const addGridBtn = document.querySelector('.add-grid-btn');
            
            if (addRowBtn) {
                addRowBtn.addEventListener('click', function() {
                    const row = createRowComponent();
                    component.appendChild(row);
                    selectComponent(row);
                    updateComponentPreview();
                });
            }
            
            if (addGridBtn) {
                addGridBtn.addEventListener('click', function() {
                    const grid = createGridComponent();
                    component.appendChild(grid);
                    selectComponent(grid);
                    updateComponentPreview();
                });
            }
        }
        
        // Add event listener for update columns button
        if (component.getAttribute('data-component-type') === 'row') {
            const updateColumnsBtn = document.querySelector('.update-columns-btn');
            if (updateColumnsBtn) {
                updateColumnsBtn.addEventListener('click', function() {
                    const columnsSelect = document.querySelector('[data-property="columns"]');
                    if (columnsSelect) {
                        updateRowColumns(component, parseInt(columnsSelect.value));
                        updateComponentPreview();
                    }
                });
            }
        }
    }
    
    function updateRowColumns(rowComponent, numColumns) {
        // Clear existing columns
        rowComponent.innerHTML = '';
        
        // Create new columns
        for (let i = 0; i < numColumns; i++) {
            const col = document.createElement('div');
            col.className = 'component-col';
            
            // Make columns droppable
            makeDroppable(col);
            
            rowComponent.appendChild(col);
        }
        
        // Add row controls back
        addComponentControls(rowComponent);
    }
    
    // Sample template generator based on keywords in the prompt
    function generateSampleTemplate(prompt) {
        prompt = prompt.toLowerCase();
        let template = '';
        
        if (prompt.includes('welcome')) {
            template = getWelcomeTemplate();
        } else if (prompt.includes('confirmation') || prompt.includes('booking')) {
            template = getConfirmationTemplate();
        } else if (prompt.includes('document') || prompt.includes('request')) {
            template = getDocumentRequestTemplate();
        } else if (prompt.includes('approval')) {
            template = getApprovalTemplate();
        } else {
            template = getGeneralTemplate();
    }
    
        return template;
    }
    
    // Template generation functions
    function getWelcomeTemplate() {
        return `
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f7f7; font-family: Arial, sans-serif;">
            <tr>
                <td align="center" style="padding: 40px 0;">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding: 40px 30px; text-align: center; background-color: #4a6cf7; border-radius: 8px 8px 0 0;">
                                <h1 style="color: #ffffff; margin: 0;">Welcome to Our Service</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <p style="margin-top: 0; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                                <p style="font-size: 16px; line-height: 1.6;">Thank you for registering with us! We're excited to have you on board.</p>
                                <p style="font-size: 16px; line-height: 1.6;">Your account has been created and you can now use our services. Below are your account details:</p>
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f9f9f9; border-radius: 4px; margin: 20px 0;">
                                    <tr>
                                        <td style="padding: 20px;">
                                            <p style="margin: 0; font-size: 14px;"><strong>Email:</strong> {email}</p>
                                            <p style="margin: 10px 0 0; font-size: 14px;"><strong>Account Type:</strong> Standard</p>
                                        </td>
                                    </tr>
                                </table>
                                <p style="font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="background-color: #4a6cf7; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Get Started</a>
                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 0 0 8px 8px;">
                                <p style="margin: 0; font-size: 14px; color: #666666;">© 2023 Your Company. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        `;
    }
    
    function getConfirmationTemplate() {
        return `
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f7f7; font-family: Arial, sans-serif;">
            <tr>
                <td align="center" style="padding: 40px 0;">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding: 40px 30px; text-align: center; background-color: #28a745; border-radius: 8px 8px 0 0;">
                                <h1 style="color: #ffffff; margin: 0;">Booking Confirmation</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <p style="margin-top: 0; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                                <p style="font-size: 16px; line-height: 1.6;">Your booking has been confirmed! Here are the details:</p>
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f9f9f9; border-radius: 4px; margin: 20px 0;">
                                    <tr>
                                        <td style="padding: 20px;">
                                            <p style="margin: 0; font-size: 14px;"><strong>Date:</strong> {booking_date}</p>
                                            <p style="margin: 10px 0 0; font-size: 14px;"><strong>Time:</strong> {booking_time}</p>
                                            <p style="margin: 10px 0 0; font-size: 14px;"><strong>Service:</strong> Consultation</p>
                                            <p style="margin: 10px 0 0; font-size: 14px;"><strong>Location:</strong> Main Office</p>
                                        </td>
                                    </tr>
                                </table>
                                <p style="font-size: 16px; line-height: 1.6;">Please arrive 10 minutes before your scheduled time. If you need to reschedule or cancel, please do so at least 24 hours in advance.</p>
                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="background-color: #28a745; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Manage Booking</a>
                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 0 0 8px 8px;">
                                <p style="margin: 0; font-size: 14px; color: #666666;">© 2023 Your Company. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        `;
    }
    
    function getDocumentRequestTemplate() {
        return `
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f7f7; font-family: Arial, sans-serif;">
            <tr>
                <td align="center" style="padding: 40px 0;">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding: 40px 30px; text-align: center; background-color: #f7941d; border-radius: 8px 8px 0 0;">
                                <h1 style="color: #ffffff; margin: 0;">Document Request</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <p style="margin-top: 0; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                                <p style="font-size: 16px; line-height: 1.6;">We need some additional documents to proceed with your application. Please submit the following documents:</p>
                                <ul style="font-size: 16px; line-height: 1.6;">
                                    <li>Proof of identity (passport or national ID)</li>
                                    <li>Proof of address (utility bill or bank statement, not older than 3 months)</li>
                                    <li>Recent photograph (passport style)</li>
                </ul>
                                <p style="font-size: 16px; line-height: 1.6;">You can upload these documents directly through your account portal or respond to this email with the attachments.</p>
                                <p style="font-size: 16px; line-height: 1.6;">Please submit these documents within 7 days to avoid delays in processing your application.</p>
                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="background-color: #f7941d; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Upload Documents</a>
                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 0 0 8px 8px;">
                                <p style="margin: 0; font-size: 14px; color: #666666;">© 2023 Your Company. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        `;
    }
    
    function getApprovalTemplate() {
        return `
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f7f7; font-family: Arial, sans-serif;">
            <tr>
                <td align="center" style="padding: 40px 0;">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding: 40px 30px; text-align: center; background-color: #28a745; border-radius: 8px 8px 0 0;">
                                <h1 style="color: #ffffff; margin: 0;">Application Approved</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <p style="margin-top: 0; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                                <p style="font-size: 16px; line-height: 1.6;">Great news! Your application has been approved.</p>
                                <p style="font-size: 16px; line-height: 1.6;">Your application status has been updated to: <strong>{application_status}</strong></p>
                                <p style="font-size: 16px; line-height: 1.6;">The next steps in the process are:</p>
                                <ol style="font-size: 16px; line-height: 1.6;">
                                    <li>Complete the acceptance form</li>
                                    <li>Make the initial payment</li>
                                    <li>Schedule your onboarding session</li>
                    </ol>
                                <p style="font-size: 16px; line-height: 1.6;">Please log in to your account to complete these steps within the next 7 days.</p>
                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="background-color: #28a745; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Complete Next Steps</a>
                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 0 0 8px 8px;">
                                <p style="margin: 0; font-size: 14px; color: #666666;">© 2023 Your Company. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        `;
    }
    
    function getGeneralTemplate() {
        return `
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f7f7; font-family: Arial, sans-serif;">
            <tr>
                <td align="center" style="padding: 40px 0;">
                    <table width="600" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <tr>
                            <td style="padding: 40px 30px; text-align: center; background-color: #3498db; border-radius: 8px 8px 0 0;">
                                <h1 style="color: #ffffff; margin: 0;">Important Information</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 30px;">
                                <p style="margin-top: 0; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                                <p style="font-size: 16px; line-height: 1.6;">We hope this email finds you well. We wanted to share some important information with you regarding your account.</p>
                                <p style="font-size: 16px; line-height: 1.6;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla quam velit, vulputate eu pharetra nec, mattis ac neque. Duis vulputate commodo lectus, ac blandit elit.</p>
                                <p style="font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, please don't hesitate to contact us.</p>
                <div style="text-align: center; margin-top: 30px;">
                                    <a href="#" style="background-color: #3498db; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Learn More</a>
                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-radius: 0 0 8px 8px;">
                                <p style="margin: 0; font-size: 14px; color: #666666;">© 2023 Your Company. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        `;
    }

    // Initialize the code preview on page load
    if (aiTemplateContent && aiCodePreview) {
            updateAICodePreview();
        }

    // Add CSS to properly support component properties panel
    const style = document.createElement('style');
    style.textContent = `
        .property-group {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .property-group h5 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .property-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .property-row label {
            flex: 0 0 40%;
            font-size: 13px;
            color: #555;
        }
        
        .property-row input, 
        .property-row select {
            flex: 0 0 55%;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .property-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            margin-top: 10px;
            width: 100%;
        }
        
        .property-btn:hover {
            background-color: var(--primary-color-dark);
        }
        
        .canvas-component {
            position: relative;
            margin-bottom: 10px;
            min-height: 30px;
            border: 1px dashed transparent;
        }
        
        .canvas-component:hover {
            border-color: #ccc;
        }
        
        .canvas-component.selected {
            border-color: var(--primary-color);
        }
        
        .component-controls {
            position: absolute;
            top: 5px;
            right: 5px;
            display: none;
            background-color: rgba(255,255,255,0.9);
            border-radius: 3px;
            padding: 3px;
            z-index: 10;
        }
        
        .canvas-component:hover .component-controls,
        .canvas-component.selected .component-controls {
            display: flex;
        }
        
        .component-control-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 5px;
            color: #555;
            font-size: 12px;
        }
        
        .component-control-btn:hover {
            color: var(--primary-color);
        }
        
        .canvas-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .canvas-container {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            height: 600px;
            overflow-y: auto;
        }
        
        .component-preview-area {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            height: 520px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .canvas-preview h4 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .code-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .code-editor-container,
        .code-preview-container {
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .editor-header,
        .preview-header {
            padding: 10px;
            background-color: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .editor-header h4,
        .preview-header h4 {
            margin: 0;
        }
        
        .code-editor {
            border: none;
            border-radius: 0 0 4px 4px;
            resize: none;
            font-family: monospace;
            padding: 15px;
        }
        
        .email-preview {
            height: 500px;
            overflow-y: auto;
            padding: 15px;
        }
        
        .ai-builder-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .ai-prompt-section {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .ai-prompt-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .ai-suggestions {
            margin-top: 20px;
        }
        
        .ai-suggestion-tag {
            display: inline-block;
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            padding: 5px 8px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 13px;
            cursor: pointer;
        }
        
        .ai-suggestion-tag:hover {
            background-color: #dde2e6;
        }
        
        .ai-preview-container {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
        }
        
        .ai-generating {
            text-align: center;
            padding: 50px 0;
        }
        
        .ai-generating-spinner {
            margin-bottom: 15px;
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .component-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .component-col {
            flex: 1;
            min-height: 40px;
            border: 1px dashed #eee;
            padding: 5px;
        }
    `;
    document.head.appendChild(style);
});