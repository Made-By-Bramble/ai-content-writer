# AI Content Writer for Craft CMS

**Transform your content creation with AI-powered writing assistance directly in Craft CMS**

Revolutionize your content workflow with intelligent AI-generated text for any field type. This plugin seamlessly integrates OpenAI's advanced language models into your entry editor, providing instant content generation that saves time while maintaining quality and consistency across your website.

![Plugin Version](https://img.shields.io/badge/version-1.0.0-blue)
![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.3%2B-orange)
![License](https://img.shields.io/badge/license-Proprietary-red)

## 🌟 Why This Plugin?

**The Problem:** Content creation is time-consuming and resource-intensive. Writers face creative blocks, and maintaining consistent tone and quality across large amounts of content is challenging.

**The Solution:** This plugin uses OpenAI's cutting-edge language models to generate high-quality content on demand, directly within your Craft CMS entry editor. It provides:
- ✅ Instant content generation for any field type
- ✅ Consistent tone and quality across your content
- ✅ Seamless integration with your existing workflow  
- ✅ Preview and refine content before publishing
- ✅ Support for multiple content formats (plain text, HTML, etc.)

## 🚀 Key Features

### **🤖 Smart Content Generation**
- Generate content directly in the entry editor sidebar
- Uses OpenAI's latest language models (GPT-5, GPT-4o, etc.)
- Context-aware generation based on entry type and field
- Support for different content formats and styles

### **⚙️ Flexible Field Support**
- **Plain Text Fields**: Simple text content generation
- **Rich Text Editors**: HTML-formatted content (Redactor, CKEditor)
- **Table Fields**: Structured data content
- **Matrix Fields**: Full support for matrix block entries via slideout editing
- Dynamic field detection per entry type

### **🎯 Intelligent Integration**
- Entry editor sidebar panel for main entries and matrix blocks
- Dynamic field selection based on entry type
- Seamless slideout support for matrix field editing
- Real-time content preview before insertion
- One-click content insertion with format detection

### **📊 Powerful Management**
- Configure content generation per entry type
- Background job processing for content generation tasks
- Dashboard widget for usage statistics
- Comprehensive error handling and logging

### **🔧 Advanced Controls**
- Multiple OpenAI model options with auto-loading
- Configurable token limits and generation parameters
- Custom system prompts for brand voice consistency
- Environment variable support for secure API key management

## 📋 Requirements

- **Craft CMS**: Version 5.3.0 or higher
- **PHP**: Version 8.1 or higher
- **OpenAI API Key**: Required for all content generation
- **Internet Connection**: For OpenAI API communication
- **Entry Types**: At least one configured entry type with compatible fields

## 🔧 Installation

### Via Craft Plugin Store (Coming Soon)

1. **Open the Plugin Store**
   - In your Craft CMS admin panel, go to **Settings → Plugin Store**
   - Search for "AI Content Writer"
   - Click **Install** on the plugin

2. **Install the Plugin**
   - Click **Try** for a free trial or **Buy Now** to purchase
   - The plugin will be automatically downloaded and installed
   - Configuration required after installation

### Via Composer

1. **Install via Composer**
   ```bash
   composer require madebybramble/craft-ai-content-writer
   ```

2. **Install in Craft CMS**
   - Go to **Settings → Plugins**
   - Find "AI Content Writer"
   - Click **Install**

## ⚡ Quick Start Guide

### 1. **Get Your OpenAI API Key**
   - Visit [OpenAI Platform](https://platform.openai.com/)
   - Create account or sign in
   - Navigate to **API Keys** section
   - Create a new API key and copy it

### 2. **Configure the Plugin**
   - Go to **Settings → Plugins → AI Content Writer**
   - Enter your OpenAI API Key
   - Click **Test Connection** to verify setup
   - Choose your preferred model (GPT-5 recommended)

### 3. **Set Up Entry Types**
   - In the **Entry Type Configuration** section
   - Enable content generation for desired entry types
   - Review available compatible fields
   - Configure field type support as needed

### 4. **Test Content Generation**
   - Edit an entry of a configured type
   - Find the "AI Content Writer" panel in the sidebar
   - Select a field and enter a prompt
   - Click "Generate Content" and preview the result
   - Click "Insert into Field" to add the content

## 📖 Detailed Configuration

### **🔑 API Configuration**

#### OpenAI API Key
- **Required**: Yes
- **Format**: Your OpenAI API key or environment variable (`$OPENAI_API_KEY`)
- **Security**: Store in environment variables for production

#### Model Selection
Choose from available OpenAI language models:
- **GPT-5** (Latest): Best quality and capability ⭐ *Recommended*
- **GPT-4o**: Excellent performance and reliability
- **GPT-4-Turbo**: Fast processing with good quality
- **Other models**: Additional options based on availability

### **✍️ Content Generation Settings**

#### Token Limits
- **Range**: 100-4000 tokens per generation
- **Default**: 2000 tokens
- **Impact**: Higher limits allow longer content but increase costs

#### Custom System Prompt
- **Default**: Professional content writing prompt
- **Custom**: Override for specific brand voice or requirements
- **Context**: Automatically includes entry type and field context

### **📝 Entry Type Configuration**

#### Per-Type Settings
Configure each entry type independently:

- **Enable Generation**: Turn on/off content generation for specific entry types
- **Compatible Fields**: Automatically detected based on field type support
- **Field Types Supported**:
  - Plain Text fields
  - Rich text editors (Redactor, CKEditor)
  - Table fields

#### Field Type Support
Control which field types can receive generated content:
- **Plain Text**: Simple text content
- **Redactor**: Rich HTML content with formatting
- **CKEditor**: Advanced rich text editing
- **Table**: Structured content (manual insertion)
- **Matrix Blocks**: All supported field types within matrix block entries

### **🔧 Advanced Settings**

#### Performance Configuration
- **Max Retries**: 1-10 attempts for failed requests (default: 3)
- **API Timeout**: 10-120 seconds per request (default: 60)
- **Background Jobs**: Queue processing for content generation tasks

#### Error Handling
- Comprehensive retry logic with exponential backoff
- Detailed error logging and reporting
- Graceful fallback for API failures

## 🎯 Using Content Generation

### **From Entry Editor**

#### Basic Usage
1. Open any entry for editing (or matrix block via slideout)
2. Locate the "AI Content Writer" panel in the sidebar
3. Select your target field from the dropdown
4. Enter a descriptive prompt for the content you need
5. Click "Generate Content" to create AI-powered text
6. Review the generated content in the preview area
7. Click "Insert into Field" to add it to your entry

#### Matrix Field Support
The plugin fully supports content generation in matrix block entries:
- **Slideout Integration**: Works seamlessly in Craft's matrix field slideout editors
- **Entry Type Detection**: Automatically detects matrix block entry types and available fields
- **All Field Types**: Supports all compatible field types within matrix blocks
- **Context Awareness**: Understands matrix block context for better content generation

#### Field Selection
- **Dynamic Loading**: Available fields are loaded automatically based on entry type
- **Field Compatibility**: Only supported field types appear in the dropdown
- **Real-time Updates**: Field list updates when switching entry types

### **Background Processing**

#### Job Queue Integration
- Content generation tasks can use Craft's job queue
- Progress tracking for generation jobs
- Error recovery and retry mechanisms
- Comprehensive logging and status monitoring

## 📊 Dashboard Widget

### **Content Generation Stats Widget**
Add to your dashboard for quick monitoring:

- **Total Entry Types**: Count of all entry types in the system
- **Enabled for Generation**: Number of entry types configured for content generation
- **Supported Field Types**: Count of field types that can receive generated content
- **Configuration Status**: Identify entry types that need configuration

### Adding the Widget
1. Go to **Dashboard**
2. Click **+ New Widget**
3. Select **AI Content Writer Stats**
4. Configure size and position

## 🎯 Best Practices

### **📝 Content Quality**
- Use clear, specific prompts for better results
- Include context about your audience and purpose
- Review generated content before publishing
- Maintain your brand voice through custom system prompts

### **⚙️ Configuration Tips**
- Start with GPT-5 model for best quality
- Enable generation only for frequently used entry types
- Test with representative prompts before implementing
- Use environment variables for API keys in production

### **⚡ Performance Optimization**
- Monitor token usage to control costs
- Use appropriate timeout settings for your content complexity
- Schedule content generation during off-peak hours if needed
- Enable only needed entry types to reduce processing

### **🔒 Security & Compliance**
- Store API keys in environment variables
- Regularly rotate API keys
- Monitor API usage and costs
- Review generated content for accuracy and compliance

## 🔍 Troubleshooting

### **Common Issues**

#### "API Key Not Configured"
- ✅ Verify API key is entered correctly in settings
- ✅ Check environment variable syntax if using `$OPENAI_API_KEY`
- ✅ Ensure API key has proper permissions on OpenAI platform
- ✅ Test connection in plugin settings

#### "No Fields Available"
- ✅ Verify entry type has compatible fields
- ✅ Check that field type support is enabled for desired field types
- ✅ Confirm entry type has content generation enabled
- ✅ Review field layout configuration

#### "Content Generation Failed"
- ✅ Check internet connectivity to OpenAI API
- ✅ Verify OpenAI service status
- ✅ Review prompt content for policy compliance
- ✅ Check error logs for specific failure details

#### "Content Not Inserting"
- ✅ Verify field type is supported for automatic insertion
- ✅ Check that field exists and is editable
- ✅ Review browser console for JavaScript errors
- ✅ Confirm proper field selector configuration
- ✅ For matrix blocks: Ensure slideout editor is fully loaded before generating content

### **Performance Issues**

#### Slow Generation
- Try a faster model like GPT-4o-mini
- Reduce token limit for shorter content
- Check network connectivity
- Monitor OpenAI API status

#### High Costs
- Use more economical models
- Reduce token limits
- Optimize prompt efficiency
- Monitor usage patterns

## 📈 Understanding Field Types

### **Supported Field Types**
- **Plain Text**: Direct text insertion with automatic formatting
- **Redactor**: HTML content with rich text editor integration
- **CKEditor**: Advanced rich text with full formatting support
- **Table**: Structured content (requires manual organization)
- **Matrix Blocks**: All above field types supported within matrix block entries

### **Field Insertion Methods**
- **Direct**: Simple text fields with immediate insertion
- **API Integration**: Rich text editors using their JavaScript APIs  
- **Slideout Context**: Full support for matrix block field insertion
- **Manual**: Complex fields requiring user interaction for final placement

## 🔐 Environment Variables

For production deployments, use environment variables:

```bash
# .env file
OPENAI_API_KEY=sk-your-api-key-here
```

Then in settings, use:
- API Key: `$OPENAI_API_KEY`

## 📞 Support & Resources

### **Getting Help**
- **Documentation**: This README file
- **Issues**: [GitHub Issues](https://github.com/Made-By-Bramble/craft-ai-content-writer/issues)
- **Email Support**: [support@madebybramble.co.uk](mailto:support@madebybramble.co.uk)

### **Useful Resources**
- [OpenAI API Documentation](https://platform.openai.com/docs)
- [Craft CMS Documentation](https://craftcms.com/docs/)
- [Content Writing Best Practices](https://craftcms.com/docs/5.x/entries.html)

### **Developer Info**
- **Developer**: [Made By Bramble](https://www.madebybramble.co.uk)
- **Author**: Phill Morgan
- **License**: Proprietary

## 🎉 You're All Set!

Your Craft CMS site is now equipped with AI-powered content generation. Create compelling content faster while maintaining quality and consistency across your entire website.

**Next Steps:**
1. Configure entry types for your most-used content
2. Test content generation with various prompts
3. Set up dashboard widgets for usage monitoring
4. Train your team on effective prompt writing
5. Enjoy faster, more efficient content creation!

---

*Made with ❤️ by [Made By Bramble](https://www.madebybramble.co.uk)*