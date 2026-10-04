#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "quad.glsl"

// The 8 views of a frame: the picture, the offsets of the picture from the point of the thing, the size, the mirror.
struct SpriteView
{
    uint picture;
    int leftOffset;
    int topOffset;
    uint width;
    uint height;
    uint flipped;
    uint padding0;
    uint padding1;
};

layout(std430, set = 0, binding = 8) readonly buffer SpriteViews
{
    SpriteView views[];
};

// xyz: the feet of the thing, w: the direction the thing faces
struct SpriteInstance
{
    vec4 position;
    uint frame;
    uint light;
    uint flags;
    uint padding;
};

layout(std430, set = 0, binding = 9) readonly buffer SpriteInstances
{
    SpriteInstance instances[];
};

layout(location = 0) out vec2 outTexel;
layout(location = 1) flat out uint outPicture;
layout(location = 2) flat out uint outLight;
layout(location = 3) flat out uint outFullBright;
layout(location = 4) flat out uvec2 outSize;
layout(location = 5) out vec3 outWorld;

const float PI = 3.14159265359;

// A sprite is a flat picture that faces the screen. The side that it shows depends on where the viewer stands.
void main()
{
    SpriteInstance instance = instances[gl_InstanceIndex];
    vec2 corner = CORNERS[gl_VertexIndex];

    vec2 fromViewer = instance.position.xy - frame.eye.xy;
    float relative = atan(fromViewer.y, fromViewer.x) - instance.position.w;
    int side = int(floor(relative / (PI / 4.0) + 4.5));
    side = ((side % 8) + 8) % 8;

    SpriteView view = views[instance.frame * 8u + uint(side)];

    float width = float(view.width);
    float height = float(view.height);
    float left = view.flipped != 0u ? float(view.leftOffset) - width : -float(view.leftOffset);

    vec2 right = vec2(sin(frame.view.x), -cos(frame.view.x));
    float top = instance.position.z + float(view.topOffset);

    vec3 world = vec3(instance.position.xy + right * (left + corner.x * width), top - (1.0 - corner.y) * height);

    gl_Position = frame.viewProjection * vec4(world, 1.0);
    outWorld = world;

    float column = view.flipped != 0u ? 1.0 - corner.x : corner.x;

    outTexel = vec2(column * width, (1.0 - corner.y) * height);
    outPicture = view.picture;
    outLight = instance.light;
    outFullBright = instance.flags & 1u;
    outSize = uvec2(view.width, view.height);
}
